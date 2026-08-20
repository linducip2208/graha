<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\NumberSequence;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\User;
use App\Services\ProgressBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProgressBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_calculates_retention_advance_and_posts_balanced_journal(): void
    {
        [$project,$user,$company] = $this->fixture();
        $service = app(ProgressBillingService::class);
        $billing = $service->create($project, ['number' => 'PB-1', 'billing_date' => '2026-08-21', 'progress_percent' => '10', 'gross_amount' => '1000', 'retention_percent' => '5', 'advance_recovery' => '100', 'idempotency_key' => 'pb-1'], $user);
        $this->assertSame('50.00', $billing->retention_amount);
        $this->assertSame('850.00', $billing->net_receivable);
        $workflow = ApprovalWorkflow::create(['company_id' => $company->id, 'name' => 'Billing', 'document_type' => 'progress_billing']);
        ApprovalRequest::create(['company_id' => $company->id, 'approval_workflow_id' => $workflow->id, 'approvable_type' => ProgressBilling::class, 'approvable_id' => $billing->id, 'submitted_by' => $user->id, 'status' => 'approved', 'idempotency_key' => 'approval-pb', 'submitted_at' => now(), 'completed_at' => now()]);
        $service->activateApproved($billing, $user);
        $accounts = [];
        foreach ([['AR', 'asset', 'debit'], ['RET', 'asset', 'debit'], ['ADV', 'liability', 'credit'], ['REV', 'revenue', 'credit']] as [$code,$type,$normal]) {
            $accounts[$code] = Account::create(['company_id' => $company->id, 'code' => $code, 'name' => $code, 'type' => $type, 'normal_balance' => $normal]);
        }
        foreach ([['ar_debit', 'AR'], ['retention_debit', 'RET'], ['advance_debit', 'ADV'], ['revenue_credit', 'REV']] as [$side,$code]) {
            AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'progress_billing', 'entry_side' => $side, 'account_id' => $accounts[$code]->id]);
        }
        $posted = $service->post($billing->refresh(), $user);
        $journal = $posted->journal()->with('entries')->first();
        $debit = $journal->entries->reduce(fn ($sum, $line) => bcadd($sum, $line->debit, 2), '0');
        $credit = $journal->entries->reduce(fn ($sum, $line) => bcadd($sum, $line->credit, 2), '0');
        $this->assertSame('1000.00', $debit);
        $this->assertSame($debit, $credit);
        $this->assertSame($journal->id, $service->post($posted->refresh(), $user)->journal_id);
    }

    public function test_billing_cannot_exceed_contract_and_cannot_activate_without_approval(): void
    {
        [$project,$user] = $this->fixture();
        $service = app(ProgressBillingService::class);
        $billing = $service->create($project, ['number' => 'PB-1', 'billing_date' => '2026-08-21', 'progress_percent' => '90', 'gross_amount' => '9000', 'retention_percent' => '0', 'advance_recovery' => '0', 'idempotency_key' => 'pb-1'], $user);
        try {
            $service->activateApproved($billing, $user);
            $this->fail('Gate harus menolak.');
        } catch (ValidationException) {
            $this->assertSame('draft', $billing->refresh()->status);
        }
        $this->expectException(ValidationException::class);
        $service->create($project, ['number' => 'PB-2', 'billing_date' => '2026-08-21', 'progress_percent' => '20', 'gross_amount' => '2000', 'retention_percent' => '0', 'advance_recovery' => '0', 'idempotency_key' => 'pb-2'], $user);
    }

    private function fixture(): array
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $user = User::factory()->create();
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C', 'name' => 'Client']);
        $project = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P', 'name' => 'Project', 'contract_value' => '10000', 'status' => 'active']);
        FiscalPeriod::create(['company_id' => $company->id, 'name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        NumberSequence::create(['company_id' => $company->id, 'document_type' => 'journal', 'prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);

        return [$project, $user, $company];
    }
}
