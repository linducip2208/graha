<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\NumberSequence;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\User;
use App\Services\CashBankService;
use App\Services\FiscalPeriodClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CashBankClosingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_receipt_is_capped_idempotent_and_reconciled(): void
    {
        [$company, $user, $period, $bank, $billing] = $this->fixture();
        $service = app(CashBankService::class);

        $receipt = $service->receiveCustomer($billing, $bank, '600.00', '2026-08-21', 'RC-001', 'TRANSFER-1', 'receipt-1', $user);
        $duplicate = $service->receiveCustomer($billing, $bank, '600.00', '2026-08-21', 'RC-001', 'TRANSFER-1', 'receipt-1', $user);

        $this->assertSame($receipt->id, $duplicate->id);
        $this->assertDatabaseHas('journals', ['id' => $receipt->journal_id, 'status' => 'posted']);

        $line = BankStatementLine::create([
            'bank_account_id' => $bank->id,
            'transaction_date' => '2026-08-21',
            'reference' => 'BANK-1',
            'description' => 'Pembayaran pelanggan',
            'amount' => '600.00',
        ]);
        $matched = $service->reconcile($line, 'customer_receipt', $receipt->id, $user);
        $this->assertSame('reconciled', $matched->status);

        $this->expectException(ValidationException::class);
        $service->receiveCustomer($billing, $bank, '500.00', '2026-08-21', 'RC-002', 'TRANSFER-2', 'receipt-2', $user);
    }

    public function test_period_close_requires_approval_and_reconciled_statements(): void
    {
        [$company, $user, $period, $bank] = $this->fixture();
        $service = app(FiscalPeriodClosingService::class);

        try {
            $service->close($period, $user);
            $this->fail('Periode tanpa approval harus ditolak.');
        } catch (ValidationException) {
            $this->assertSame('open', $period->refresh()->status);
        }

        $workflow = ApprovalWorkflow::create(['company_id' => $company->id, 'name' => 'Period Close', 'document_type' => 'fiscal_period_close']);
        ApprovalRequest::create([
            'company_id' => $company->id,
            'approval_workflow_id' => $workflow->id,
            'approvable_type' => FiscalPeriod::class,
            'approvable_id' => $period->id,
            'submitted_by' => $user->id,
            'status' => 'approved',
            'idempotency_key' => 'close-approval-1',
            'submitted_at' => now(),
            'completed_at' => now(),
        ]);
        BankStatementLine::create(['bank_account_id' => $bank->id, 'transaction_date' => '2026-08-20', 'reference' => 'UNMATCHED', 'amount' => '10.00']);

        $this->expectException(ValidationException::class);
        $service->close($period->refresh(), $user);
    }

    private function fixture(): array
    {
        $company = Company::create(['code' => 'GP', 'name' => 'Graha Pondasi']);
        $user = User::factory()->create();
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C-1', 'name' => 'Pelanggan']);
        $project = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P-1', 'name' => 'Proyek', 'contract_value' => '1000', 'status' => 'active']);
        $period = FiscalPeriod::create(['company_id' => $company->id, 'name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        NumberSequence::create(['company_id' => $company->id, 'document_type' => 'journal', 'prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);

        $bankGl = Account::create(['company_id' => $company->id, 'code' => 'BANK', 'name' => 'Bank', 'type' => 'asset', 'normal_balance' => 'debit']);
        $ar = Account::create(['company_id' => $company->id, 'code' => 'AR', 'name' => 'Piutang', 'type' => 'asset', 'normal_balance' => 'debit']);
        AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'customer_receipt', 'entry_side' => 'ar_credit', 'account_id' => $ar->id]);
        $bank = BankAccount::create(['company_id' => $company->id, 'account_id' => $bankGl->id, 'code' => 'BCA', 'bank_name' => 'Bank', 'account_name' => 'PT GP', 'account_number' => '001']);
        $billing = ProgressBilling::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'number' => 'PB-001',
            'billing_date' => '2026-08-01',
            'progress_percent' => '100',
            'gross_amount' => '1000',
            'retention_percent' => '0',
            'retention_amount' => '0',
            'advance_recovery' => '0',
            'net_receivable' => '1000',
            'status' => 'posted',
            'created_by' => $user->id,
            'idempotency_key' => 'billing-1',
        ]);

        return [$company, $user, $period, $bank, $billing];
    }
}
