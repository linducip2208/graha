<?php

namespace App\Http\Controllers;

use App\Contracts\ApprovalSyncable;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalEngine;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalController extends Controller
{
    public function index(Request $request, CurrentCompany $current)
    {
        $id = $current->id();
        $pending = ApprovalRequest::where('company_id', $id)->where('status', 'pending')->with(['workflow', 'decisions'])->latest()->get();
        $history = ApprovalRequest::where('company_id', $id)->where('status', '!=', 'pending')->with('workflow')->latest('completed_at')->limit(100)->get();

        return view('approvals.index', [
            'pending' => $pending,
            'history' => $history,
            'workflows' => ApprovalWorkflow::where('company_id', $id)->with('steps.role')->orderBy('document_type')->get(),
            'roles' => Role::where('company_id', $id)->orderBy('name')->get(),
            'users' => User::whereHas('companies', fn ($query) => $query->whereKey($id))->orderBy('name')->get(),
            'delegations' => ApprovalDelegation::where('company_id', $id)->with(['delegator', 'delegate', 'role'])->latest()->get(),
            'kanban' => $request->query('view') === 'kanban' ? $this->kanban($pending, $history) : null,
        ]);
    }

    /** Papan kanban status approval, dibangun dari koleksi yang sudah dimuat. */
    private function kanban($pending, $history): array
    {
        $columns = [];
        $columns[] = ['label' => 'Pending', 'items' => $pending->map(fn ($a) => [
            'title' => $a->workflow->name,
            'subtitle' => class_basename($a->approvable_type).' #'.$a->approvable_id,
            'meta' => $a->currency.' '.number_format((float) $a->amount, 0),
            'href' => '/admin/approvals',
        ])->values()];
        foreach (['approved' => 'Disetujui', 'rejected' => 'Ditolak', 'revision_requested' => 'Revisi'] as $status => $label) {
            $columns[] = ['label' => $label, 'items' => $history->where('status', $status)->take(20)->map(fn ($a) => [
                'title' => $a->workflow->name,
                'subtitle' => class_basename($a->approvable_type).' #'.$a->approvable_id,
                'meta' => $a->completed_at?->format('d/m/y') ?? strtoupper($a->status),
                'href' => '/admin/approvals',
            ])->values()];
        }

        return $columns;
    }

    public function workflow(Request $request, CurrentCompany $current)
    {
        $data = $request->validate([
            'name' => ['required', 'max:255'], 'document_type' => ['required', 'max:80'],
            'mode' => ['required', 'in:sequential,parallel'], 'min_amount' => ['nullable', 'decimal:0,2', 'min:0'],
            'max_amount' => ['nullable', 'decimal:0,2', 'gte:min_amount'], 'currency' => ['nullable', 'size:3'],
            'role_id' => ['required', 'exists:roles,id'], 'step_mode' => ['required', 'in:any,all,quorum'],
            'quorum' => ['nullable', 'integer', 'min:1'], 'sla_hours' => ['nullable', 'integer', 'min:1'],
        ]);
        abort_unless(Role::where('company_id', $current->id())->whereKey($data['role_id'])->exists(), 422);
        if ($data['step_mode'] === 'quorum' && empty($data['quorum'])) {
            throw ValidationException::withMessages(['quorum' => 'Quorum wajib diisi.']);
        }
        DB::transaction(function () use ($data, $current): void {
            $workflow = ApprovalWorkflow::create(['company_id' => $current->id(), 'name' => $data['name'], 'document_type' => $data['document_type'], 'mode' => $data['mode'], 'min_amount' => $data['min_amount'] ?? null, 'max_amount' => $data['max_amount'] ?? null, 'currency' => filled($data['currency'] ?? null) ? strtoupper($data['currency']) : null]);
            $workflow->steps()->create(['sequence' => 1, 'action' => 'approve', 'role_id' => $data['role_id'], 'mode' => $data['step_mode'], 'quorum' => $data['step_mode'] === 'quorum' ? $data['quorum'] : null, 'sla_hours' => $data['sla_hours'] ?? null]);
        }, 3);

        return back()->with('status', 'Workflow approval dibuat.');
    }

    public function step(Request $request, ApprovalWorkflow $workflow, CurrentCompany $current)
    {
        abort_unless($workflow->company_id === $current->id(), 404);
        $data = $request->validate(['role_id' => ['required', 'exists:roles,id'], 'mode' => ['required', 'in:any,all,quorum'], 'quorum' => ['nullable', 'integer', 'min:1'], 'sla_hours' => ['nullable', 'integer', 'min:1']]);
        abort_unless(Role::where('company_id', $current->id())->whereKey($data['role_id'])->exists(), 422);
        if ($data['mode'] === 'quorum' && empty($data['quorum'])) {
            throw ValidationException::withMessages(['quorum' => 'Quorum wajib diisi.']);
        }
        $sequence = ((int) $workflow->steps()->max('sequence')) + 1;
        $workflow->steps()->create([...$data, 'sequence' => $sequence, 'action' => 'approve', 'quorum' => $data['mode'] === 'quorum' ? $data['quorum'] : null]);

        return back()->with('status', 'Tahap approval ditambahkan.');
    }

    public function decide(Request $request, ApprovalRequest $approval, CurrentCompany $current, ApprovalEngine $engine)
    {
        abort_unless($approval->company_id === $current->id(), 404);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject,request_revision'], 'comment' => ['nullable', 'max:1000']]);
        if ($data['decision'] !== 'approve' && blank($data['comment'])) {
            throw ValidationException::withMessages(['comment' => 'Komentar wajib untuk reject/revision.']);
        }
        match ($data['decision']) {
            'approve' => $engine->approve($approval, $request->user(), $data['comment'] ?? null),
            'reject' => $engine->reject($approval, $request->user(), $data['comment']),
            'request_revision' => $engine->requestRevision($approval, $request->user(), $data['comment']),
        };
        if ($approval->approvable instanceof ApprovalSyncable) {
            $approval->approvable->syncApprovalStatus($data['decision']);
        }

        return back()->with('status', 'Keputusan approval dicatat immutable.');
    }

    public function delegation(Request $request, CurrentCompany $current)
    {
        $data = $request->validate(['delegator_id' => ['required', 'exists:users,id'], 'delegate_id' => ['required', 'different:delegator_id', 'exists:users,id'], 'role_id' => ['nullable', 'exists:roles,id'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'reason' => ['required', 'max:1000']]);
        foreach ([$data['delegator_id'], $data['delegate_id']] as $userId) {
            abort_unless(User::whereKey($userId)->whereHas('companies', fn ($query) => $query->whereKey($current->id()))->exists(), 422);
        }
        if ($request->user()->id === (int) $data['delegator_id']) {
            throw ValidationException::withMessages(['delegator_id' => 'Delegasi harus disahkan pihak berwenang lain.']);
        }
        if (filled($data['role_id'] ?? null)) {
            abort_unless(Role::where('company_id', $current->id())->whereKey($data['role_id'])->exists(), 422);
        }
        ApprovalDelegation::create([...$data, 'company_id' => $current->id(), 'approved_by' => $request->user()->id]);

        return back()->with('status', 'Delegasi aktif sesuai periode.');
    }
}
