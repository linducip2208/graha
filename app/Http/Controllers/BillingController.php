<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\RetentionRelease;
use App\Models\TaxRate;
use App\Services\ApprovalEngine;
use App\Services\ProgressBillingService;
use App\Services\RetentionReleaseService;
use App\Support\Tenancy\CurrentCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index(CurrentCompany $current)
    {
        return view('billing.index', ['projects' => Project::where('company_id', $current->id())->whereIn('status', ['active', 'in_progress'])->orderBy('name')->get(), 'billings' => ProgressBilling::where('company_id', $current->id())->with(['project', 'journal'])->latest('billing_date')->get(), 'releases' => RetentionRelease::where('company_id', $current->id())->with(['project', 'journal'])->latest('release_date')->get(), 'workflows' => ApprovalWorkflow::where('company_id', $current->id())->whereIn('document_type', ['progress_billing', 'retention_release'])->where('is_active', true)->get()->groupBy('document_type'), 'accounts' => Account::where('company_id', $current->id())->where('is_active', true)->orderBy('code')->get(), 'mappings' => AccountingMapping::where('company_id', $current->id())->whereIn('event_type', ['progress_billing', 'retention_release'])->with('account')->get(), 'taxRates' => TaxRate::where('company_id', $current->id())->where('kind', 'ppn_output')->where('is_active', true)->orderBy('code')->get()]);
    }

    public function store(Request $request, CurrentCompany $current, ProgressBillingService $service)
    {
        $data = $request->validate(['project_id' => ['required', 'exists:projects,id'], 'number' => ['required', 'max:80'], 'billing_date' => ['required', 'date'], 'due_date' => ['nullable', 'date', 'after_or_equal:billing_date'], 'progress_percent' => ['required', 'decimal:0,4', 'between:0.0001,100'], 'gross_amount' => ['required', 'decimal:0,2', 'gt:0'], 'retention_percent' => ['nullable', 'decimal:0,4', 'between:0,100'], 'advance_recovery' => ['required', 'decimal:0,2', 'min:0'], 'tax_rate_id' => ['nullable', 'integer'], 'idempotency_key' => ['required', 'max:120']]);
        $data['retention_percent'] ??= CompanySetting::val($current->id(), 'default_retention_percent');
        if (! empty($data['tax_rate_id'])) {
            abort_unless(TaxRate::where('company_id', $current->id())->where('kind', 'ppn_output')->where('is_active', true)->whereKey($data['tax_rate_id'])->exists(), 422);
        } else {
            unset($data['tax_rate_id']);
        }
        $project = Project::where('company_id', $current->id())->findOrFail($data['project_id']);
        $service->create($project, $data, $request->user());

        return back()->with('status', 'Draft progress billing dibuat.');
    }

    public function submit(Request $request, ProgressBilling $billing, CurrentCompany $current, ApprovalEngine $engine)
    {
        $this->owned($billing, $current);
        $data = $request->validate(['workflow_id' => ['required', 'exists:approval_workflows,id'], 'idempotency_key' => ['required', 'max:100']]);
        $workflow = ApprovalWorkflow::where('company_id', $current->id())->where('document_type', 'progress_billing')->findOrFail($data['workflow_id']);
        $approval = $engine->submit($workflow, $billing, $request->user(), $data['idempotency_key']);
        $approval->update(['amount' => $billing->gross_amount, 'currency' => 'IDR']);
        $billing->update(['status' => 'pending_approval']);

        return back()->with('status', 'Billing dikirim ke approval.');
    }

    public function activate(Request $request, ProgressBilling $billing, CurrentCompany $current, ProgressBillingService $service)
    {
        $this->owned($billing, $current);
        $service->activateApproved($billing, $request->user());

        return back()->with('status', 'Billing approval tervalidasi.');
    }

    public function post(Request $request, ProgressBilling $billing, CurrentCompany $current, ProgressBillingService $service)
    {
        $this->owned($billing, $current);
        $service->post($billing, $request->user());

        return back()->with('status', 'Billing diposting ke AR dan revenue.');
    }

    public function pdf(Request $request, ProgressBilling $billing, CurrentCompany $current)
    {
        abort_unless($billing->company_id === $current->id(), 404);
        $billing->load(['project.customer', 'taxRate']);
        $company = Company::find($billing->company_id);
        $payload = [
            'billing' => $billing,
            'project' => $billing->project,
            'company' => $company,
            'customerName' => $billing->project?->customer?->name ?? 'Pelanggan',
            'signer' => $request->user()->name,
        ];
        if ($request->query('format') === 'thermal') {
            return view('pdf.billing-thermal', $payload);
        }
        $pdf = Pdf::loadView('pdf.billing-faktur', $payload);

        return $pdf->stream('Faktur-'.$billing->number.'.pdf');
    }

    public function storeRelease(Request $request, CurrentCompany $current, RetentionReleaseService $service)
    {
        $data = $request->validate(['project_id' => ['required', 'integer'], 'number' => ['required', 'max:80'], 'release_date' => ['required', 'date'], 'amount' => ['required', 'decimal:0,2', 'gt:0'], 'idempotency_key' => ['required', 'max:120']]);
        $project = Project::where('company_id', $current->id())->findOrFail($data['project_id']);
        $service->create($project, $data, $request->user());

        return back()->with('status', 'Draft release retensi dibuat.');
    }

    public function submitRelease(Request $request, RetentionRelease $release, CurrentCompany $current, ApprovalEngine $engine)
    {
        $this->ownedRelease($release, $current);
        $data = $request->validate(['workflow_id' => ['required', 'integer'], 'idempotency_key' => ['required', 'max:120']]);
        $workflow = ApprovalWorkflow::where('company_id', $current->id())->where('document_type', 'retention_release')->findOrFail($data['workflow_id']);
        $approval = $engine->submit($workflow, $release, $request->user(), $data['idempotency_key']);
        $approval->update(['amount' => $release->amount, 'currency' => 'IDR']);
        $release->update(['status' => 'pending_approval']);

        return back()->with('status', 'Release retensi dikirim ke approval.');
    }

    public function activateRelease(Request $request, RetentionRelease $release, CurrentCompany $current, RetentionReleaseService $service)
    {
        $this->ownedRelease($release, $current);
        $service->activateApproved($release, $request->user());

        return back()->with('status', 'Approval release retensi tervalidasi.');
    }

    public function postRelease(Request $request, RetentionRelease $release, CurrentCompany $current, RetentionReleaseService $service)
    {
        $this->ownedRelease($release, $current);
        $service->post($release, $request->user());

        return back()->with('status', 'Release retensi diposting.');
    }

    private function owned(ProgressBilling $billing, CurrentCompany $current): void
    {
        abort_unless($billing->company_id === $current->id(), 404);
    }

    private function ownedRelease(RetentionRelease $release, CurrentCompany $current): void
    {
        abort_unless($release->company_id === $current->id(), 404);
    }
}
