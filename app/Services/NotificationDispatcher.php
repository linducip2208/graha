<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\User;
use App\Notifications\ApprovalNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class NotificationDispatcher
{
    public function approversForStep(ApprovalRequest $request, ApprovalStep $step): Collection
    {
        $roleUsers = User::query()->select('users.*')->distinct()
            ->join('company_user', 'company_user.user_id', '=', 'users.id')
            ->join('company_user_role', 'company_user_role.company_user_id', '=', 'company_user.id')
            ->where('company_user.company_id', $request->company_id)
            ->where('company_user.is_active', true)
            ->where('users.is_active', true)
            ->where('company_user_role.role_id', $step->role_id)
            ->get();
        $delegates = User::query()->select('users.*')->distinct()
            ->join('approval_delegations', 'approval_delegations.delegate_id', '=', 'users.id')
            ->where('approval_delegations.company_id', $request->company_id)
            ->where('approval_delegations.starts_at', '<=', now())
            ->where('approval_delegations.ends_at', '>=', now())
            ->whereNull('approval_delegations.revoked_at')
            ->whereNotNull('approval_delegations.approved_by')
            ->where(function ($query) use ($step) {
                $query->whereNull('approval_delegations.role_id')->orWhere('approval_delegations.role_id', $step->role_id);
            })
            ->where('users.is_active', true)
            ->get();

        return $roleUsers->merge($delegates)->unique('id');
    }

    public function approvalRequested(ApprovalRequest $request): void
    {
        $step = $request->workflow->steps()->where('sequence', $request->current_sequence)->first();
        if (! $step) {
            return;
        }
        [$document, $label] = $this->describe($request);
        $this->send($this->approversForStep($request, $step), 'approval_requested', $request, [
            'document' => $document,
            'label' => $label,
            'step' => $step->name,
            'due_at' => $request->due_at?->format('d/m/Y H:i'),
            'url' => '/admin/approvals',
        ]);
    }

    public function approvalDecided(ApprovalRequest $request, string $decision, ?string $comment = null): void
    {
        [$document, $label] = $this->describe($request);
        $submitter = User::find($request->submitted_by);
        $event = match ($decision) {
            'approve' => $request->status === 'approved' ? 'approval_approved' : 'approval_advanced',
            'reject' => 'approval_rejected',
            'request_revision' => 'approval_revision_requested',
            default => 'approval_advanced',
        };
        if ($submitter && $submitter->id !== auth()->id()) {
            $this->send(collect([$submitter]), $event, $request, ['document' => $document, 'label' => $label, 'comment' => $comment, 'url' => '/admin/approvals']);
        }
        if ($decision === 'approve' && $request->status !== 'approved') {
            $next = $request->workflow->steps()->where('sequence', '>', $request->current_sequence)->first();
            if ($next) {
                $this->send($this->approversForStep($request, $next), 'approval_requested', $request, [
                    'document' => $document,
                    'label' => $label,
                    'step' => $next->name,
                    'due_at' => $request->due_at?->format('d/m/Y H:i'),
                    'url' => '/admin/approvals',
                ]);
            }
        }
    }

    public function slaOverdue(ApprovalRequest $request): void
    {
        $step = $request->workflow->steps()->where('sequence', $request->current_sequence)->first();
        if (! $step) {
            return;
        }
        [$document, $label] = $this->describe($request);
        $recipients = $this->approversForStep($request, $step)->push(User::find($request->submitted_by))->filter()->unique('id');
        $this->send($recipients, 'approval_sla_overdue', $request, [
            'document' => $document,
            'label' => $label,
            'step' => $step->name,
            'due_at' => $request->due_at?->format('d/m/Y H:i'),
            'url' => '/admin/approvals',
        ]);
    }

    private function send(Collection $recipients, string $event, ApprovalRequest $request, array $payload): void
    {
        foreach ($recipients as $recipient) {
            if ($recipient->id === auth()->id() && $event !== 'approval_sla_overdue') {
                continue;
            }
            try {
                $recipient->notify(new ApprovalNotification($event, ['request_id' => $request->id, 'company_id' => $request->company_id] + $payload));
            } catch (\Throwable $e) {
                Log::warning('Gagal mengirim notifikasi approval.', ['user_id' => $recipient->id, 'event' => $event, 'error' => $e->getMessage()]);
            }
        }
    }

    private function describe(ApprovalRequest $request): array
    {
        $subject = $request->approvable;
        $document = class_basename($request->approvable_type);
        $label = $subject?->number ?? $subject?->name ?? ('#'.$request->approvable_id);

        return [$document, $label];
    }
}
