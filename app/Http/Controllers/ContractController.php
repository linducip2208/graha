<?php

namespace App\Http\Controllers;

use App\Models\ApprovalWorkflow;
use App\Models\ContractChange;
use App\Models\Project;
use App\Services\ApprovalEngine;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ContractController extends Controller
{
    public function index(Request $request, CurrentCompany $current)
    {
        $companyId = $current->id();
        $type = $request->query('type');
        $status = $request->query('status');

        $changes = ContractChange::where('company_id', $companyId)
            ->with(['project:id,code,name', 'submitter:id,name'])
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->get();

        return view('contracts.index', [
            'changes' => $changes,
            'projects' => Project::where('company_id', $companyId)->whereIn('status', ['active', 'in_progress'])->orderBy('name')->get(),
            'workflows' => ApprovalWorkflow::where('company_id', $companyId)->where('document_type', 'contract_change')->where('is_active', true)->orderBy('name')->get(),
            'types' => ContractChange::TYPES,
            'filterType' => $type,
            'filterStatus' => $status,
        ]);
    }

    public function store(Request $request, CurrentCompany $current)
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer'],
            'number' => ['required', 'max:80'],
            'type' => ['required', 'in:'.implode(',', array_keys(ContractChange::TYPES))],
            'title' => ['required', 'max:255'],
            'description' => ['nullable', 'max:3000'],
            'currency' => ['nullable', 'size:3'],
            'amount' => ['required', 'decimal:0,2', 'gte:0'],
            'days_extension' => ['nullable', 'integer', 'min:0'],
            'effective_date' => ['nullable', 'date'],
        ]);
        if (! empty($data['project_id'])) {
            abort_unless(Project::where('company_id', $current->id())->whereKey($data['project_id'])->exists(), 422);
        }
        $change = ContractChange::create([...collect($data)->except('project_id')->all(), 'company_id' => $current->id(), 'project_id' => $data['project_id'] ?? null, 'currency' => strtoupper($data['currency'] ?? 'IDR'), 'days_extension' => $data['days_extension'] ?? 0, 'idempotency_key' => 'cc-store-'.uniqid('', true)]);

        return back()->with('status', "Perubahan kontrak {$change->number} tersimpan sebagai draft.");
    }

    public function submit(Request $request, ContractChange $contract, CurrentCompany $current, ApprovalEngine $engine)
    {
        abort_unless($contract->company_id === $current->id(), 404);
        $data = $request->validate(['workflow_id' => ['required', 'integer'], 'idempotency_key' => ['required', 'max:120']]);
        throw_if($contract->status !== 'draft', ValidationException::withMessages(['status' => 'Dokumen sudah diajukan.']));
        $workflow = ApprovalWorkflow::where('company_id', $current->id())->where('document_type', 'contract_change')->findOrFail($data['workflow_id']);
        $approval = $engine->submit($workflow, $contract, $request->user(), $data['idempotency_key']);
        $approval->update(['amount' => $contract->amount, 'currency' => $contract->currency]);
        $contract->update(['status' => 'pending_approval', 'submitted_by' => $request->user()->id]);

        return back()->with('status', "{$contract->number} dikirim ke approval.");
    }

    public function show(ContractChange $contract, CurrentCompany $current)
    {
        abort_unless($contract->company_id === $current->id(), 404);

        return view('contracts.show', [
            'contract' => $contract->load(['project', 'submitter', 'approvalRequests.workflow.steps.role', 'approvalRequests.decisions']),
            'workflows' => ApprovalWorkflow::where('company_id', $current->id())->where('document_type', 'contract_change')->where('is_active', true)->get(),
        ]);
    }
}
