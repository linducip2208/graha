<?php

namespace App\Services;

use App\Models\AccountingMapping;
use App\Models\ApprovalRequest;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgressBillingService
{
    public function __construct(private AccountingService $accounting, private AuditTrail $audit, private TaxService $tax) {}

    public function create(Project $project, array $data, User $actor): ProgressBilling
    {
        return DB::transaction(function () use ($project, $data, $actor) {
            $project = Project::lockForUpdate()->findOrFail($project->id);
            throw_unless(in_array($project->status, ['active', 'in_progress'], true), ValidationException::withMessages(['project' => 'Proyek belum aktif.']));
            $gross = (string) $data['gross_amount'];
            if (empty($data['due_date'])) {
                $data['due_date'] = Carbon::parse($data['billing_date'])->addDays($project->customer->payment_term_days ?? 30)->toDateString();
            }
            $retention = bcdiv(bcmul($gross, (string) $data['retention_percent'], 4), '100', 2);
            $advance = (string) ($data['advance_recovery'] ?? '0');
            $rate = $this->tax->resolve($project->company_id, isset($data['tax_rate_id']) && $data['tax_rate_id'] !== '' ? (int) $data['tax_rate_id'] : null, 'ppn_output');
            $tax = $this->tax->compute($gross, $rate);
            $net = bcsub(bcadd(bcsub($gross, $retention, 2), $tax, 2), $advance, 2);
            throw_if(bccomp($gross, '0', 2) <= 0 || bccomp($net, '0', 2) < 0, ValidationException::withMessages(['amount' => 'Nilai billing/net receivable tidak valid.']));
            $committed = ProgressBilling::where('project_id', $project->id)->whereNotIn('status', ['rejected', 'cancelled'])->sum('gross_amount');
            if ($project->contract_value !== null) {
                throw_if(bccomp(bcadd((string) $committed, $gross, 2), (string) $project->contract_value, 2) === 1, ValidationException::withMessages(['contract' => 'Akumulasi billing melebihi nilai kontrak.']));
            }$billing = ProgressBilling::create([...$data, 'company_id' => $project->company_id, 'project_id' => $project->id, 'retention_amount' => $retention, 'tax_rate_id' => $rate?->id, 'tax_amount' => $tax, 'net_receivable' => $net, 'created_by' => $actor->id]);
            $this->audit->record($project->company_id, $actor->id, 'billing.created', $billing);

            return $billing;
        }, 3);
    }

    public function activateApproved(ProgressBilling $billing, User $actor): ProgressBilling
    {
        return DB::transaction(function () use ($billing, $actor) {
            $billing = ProgressBilling::lockForUpdate()->findOrFail($billing->id);
            $approved = ApprovalRequest::where('approvable_type', ProgressBilling::class)->where('approvable_id', $billing->id)->where('status', 'approved')->exists();
            throw_unless($approved, ValidationException::withMessages(['approval' => 'Progress billing belum disetujui.']));
            $billing->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);

            return $billing->refresh();
        }, 3);
    }

    public function post(ProgressBilling $billing, User $actor): ProgressBilling
    {
        return DB::transaction(function () use ($billing, $actor) {
            $billing = ProgressBilling::lockForUpdate()->findOrFail($billing->id);
            if ($billing->status === 'posted' && $billing->journal_id) {
                return $billing;
            }throw_unless($billing->status === 'approved', ValidationException::withMessages(['status' => 'Billing belum approved.']));
            $maps = AccountingMapping::where('company_id', $billing->company_id)->where('event_type', 'progress_billing')->get()->keyBy('entry_side');
            foreach (['ar_debit', 'revenue_credit'] as $key) {
                throw_unless($maps->has($key), ValidationException::withMessages(['mapping' => "Mapping $key belum tersedia."]));
            }$lines = [['account_id' => $maps['ar_debit']->account_id, 'debit' => $billing->net_receivable, 'credit' => '0', 'project_id' => $billing->project_id], ['account_id' => $maps['revenue_credit']->account_id, 'debit' => '0', 'credit' => $billing->gross_amount, 'project_id' => $billing->project_id]];
            if (bccomp((string) $billing->retention_amount, '0', 2) === 1) {
                throw_unless($maps->has('retention_debit'), ValidationException::withMessages(['mapping' => 'Mapping retention_debit belum tersedia.']));
                $lines[] = ['account_id' => $maps['retention_debit']->account_id, 'debit' => $billing->retention_amount, 'credit' => '0', 'project_id' => $billing->project_id];
            }if (bccomp((string) $billing->advance_recovery, '0', 2) === 1) {
                throw_unless($maps->has('advance_debit'), ValidationException::withMessages(['mapping' => 'Mapping advance_debit belum tersedia.']));
                $lines[] = ['account_id' => $maps['advance_debit']->account_id, 'debit' => $billing->advance_recovery, 'credit' => '0', 'project_id' => $billing->project_id];
            }if (bccomp((string) $billing->tax_amount, '0', 2) === 1) {
                throw_unless($maps->has('tax_credit'), ValidationException::withMessages(['mapping' => 'Mapping tax_credit (PPN Keluaran) belum tersedia.']));
                $lines[] = ['account_id' => $maps['tax_credit']->account_id, 'debit' => '0', 'credit' => $billing->tax_amount, 'project_id' => $billing->project_id];
            }$journal = $this->accounting->post($billing->company_id, $billing->billing_date->toDateString(), 'progress_billing', (string) $billing->id, 'Progress billing '.$billing->number, $lines, 'progress-billing:'.$billing->id, $actor);
            $billing->update(['status' => 'posted', 'journal_id' => $journal->id]);

            return $billing->refresh();
        }, 3);
    }
}
