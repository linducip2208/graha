<?php

namespace App\Services;

use App\Models\ApprovalDecision;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalEngine
{
    public function __construct(private AuditTrail $audit) {}

    public function submit(ApprovalWorkflow $w, Model $subject, User $user, string $key): ApprovalRequest
    {
        return DB::transaction(function () use ($w, $subject, $user, $key) {
            if ($old = ApprovalRequest::where('company_id', $w->company_id)->where('idempotency_key', $key)->first()) {
                return $old;
            }
            throw_if(! $w->is_active || ! $w->steps()->exists(), ValidationException::withMessages(['workflow' => 'Workflow tidak valid.']));
            $first = $w->steps()->first();
            $r = ApprovalRequest::create(['company_id' => $w->company_id, 'approval_workflow_id' => $w->id, 'approvable_type' => $subject->getMorphClass(), 'approvable_id' => $subject->id, 'submitted_by' => $user->id, 'current_sequence' => $first->sequence, 'idempotency_key' => $key, 'submitted_at' => now(), 'due_at' => $first->sla_hours ? now()->addHours($first->sla_hours) : null]);
            $this->audit->record($w->company_id, $user->id, 'approval.submitted', $r);

            return $r;
        }, 3);
    }

    public function approve(ApprovalRequest $r, User $actor, ?string $comment = null): ApprovalRequest
    {
        return $this->decide($r, $actor, 'approve', $comment);
    }

    public function reject(ApprovalRequest $r, User $actor, string $comment): ApprovalRequest
    {
        return $this->decide($r, $actor, 'reject', $comment);
    }

    public function requestRevision(ApprovalRequest $r, User $actor, string $comment): ApprovalRequest
    {
        return $this->decide($r, $actor, 'request_revision', $comment);
    }

    private function decide(ApprovalRequest $r, User $actor, string $decision, ?string $comment): ApprovalRequest
    {
        return DB::transaction(function () use ($r, $actor, $decision, $comment) {
            $r = ApprovalRequest::lockForUpdate()->findOrFail($r->id);
            throw_if($r->submitted_by === $actor->id, ValidationException::withMessages(['actor' => 'Dilarang self-approval.']));
            throw_if($r->status !== 'pending', ValidationException::withMessages(['status' => 'Approval tidak lagi aktif.']));
            $step = $r->workflow->steps()->where('sequence', $r->current_sequence)->firstOrFail();
            throw_unless($this->isAuthorized($r, $step->role_id, $actor), ValidationException::withMessages(['actor' => 'Bukan approver aktif.']));
            ApprovalDecision::create(['approval_request_id' => $r->id, 'approval_step_id' => $step->id, 'decided_by' => $actor->id, 'decision' => $decision, 'comment' => $comment, 'ip_address' => request()?->ip(), 'user_agent' => request()?->userAgent(), 'decided_at' => now()]);
            if ($decision !== 'approve') {
                $r->update(['status' => $decision === 'reject' ? 'rejected' : 'revision_requested', 'completed_at' => now()]);
            } elseif ($this->stepIsComplete($r, $step)) {
                $next = $r->workflow->steps()->where('sequence', '>', $step->sequence)->first();
                $r->update($next ? ['current_sequence' => $next->sequence, 'due_at' => $next->sla_hours ? now()->addHours($next->sla_hours) : null] : ['status' => 'approved', 'completed_at' => now(), 'due_at' => null]);
            }
            $this->audit->record($r->company_id, $actor->id, 'approval.'.$decision, $r);

            return $r->refresh();
        }, 3);
    }

    private function stepIsComplete(ApprovalRequest $request, $step): bool
    {
        $approved = ApprovalDecision::where('approval_request_id', $request->id)->where('approval_step_id', $step->id)->where('decision', 'approve')->count();
        if ($step->mode === 'any') {
            return $approved >= 1;
        }
        if ($step->mode === 'quorum') {
            return $approved >= max(1, (int) $step->quorum);
        }
        $required = DB::table('company_user_role')->join('company_user', 'company_user.id', '=', 'company_user_role.company_user_id')->where('company_user.company_id', $request->company_id)->where('company_user.is_active', true)->where('company_user_role.role_id', $step->role_id)->distinct()->count('company_user.user_id');

        return $required > 0 && $approved >= $required;
    }

    private function isAuthorized(ApprovalRequest $request, ?int $roleId, User $actor): bool
    {
        $direct = DB::table('company_user_role')->join('company_user', 'company_user.id', '=', 'company_user_role.company_user_id')->where('company_user.company_id', $request->company_id)->where('company_user.user_id', $actor->id)->where('company_user.is_active', true)->where('company_user_role.role_id', $roleId)->exists();
        if ($direct) {
            return true;
        }

        return ApprovalDelegation::where('company_id', $request->company_id)->where('delegate_id', $actor->id)->where(fn ($query) => $query->whereNull('role_id')->orWhere('role_id', $roleId))->where('starts_at', '<=', now())->where('ends_at', '>=', now())->whereNull('revoked_at')->whereNotNull('approved_by')->exists();
    }
}
