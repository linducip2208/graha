<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\FiscalPeriod;
use App\Models\Item;
use App\Models\NumberSequence;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorInvoice;
use App\Services\CashBankService;
use App\Services\ProcurementAccountingService;
use App\Services\ProgressBillingService;
use App\Services\PurchaseOrderService;
use App\Services\ReceivablePayableAgingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TaxIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_billing_with_ppn_posts_balanced_journal_and_tax_in_receivable(): void
    {
        [$project, $user, $company, $accounts] = $this->billingFixture();
        $service = app(ProgressBillingService::class);
        $ppn = TaxRate::create(['company_id' => $company->id, 'code' => 'PPN11', 'name' => 'PPN 11%', 'kind' => 'ppn_output', 'rate_percent' => '11']);

        $billing = $service->create($project, ['number' => 'PB-TAX', 'billing_date' => '2026-08-21', 'progress_percent' => '10', 'gross_amount' => '1000', 'retention_percent' => '5', 'advance_recovery' => '0', 'tax_rate_id' => (string) $ppn->id, 'idempotency_key' => 'pb-tax'], $user);

        $this->assertSame('110.00', $billing->tax_amount);
        $this->assertSame('1060.00', $billing->net_receivable);

        $this->approve($company, $user, $billing);
        $service->activateApproved($billing, $user);
        $posted = $service->post($billing->refresh(), $user);
        $journal = $posted->journal()->with('entries')->first();

        $doc = Document::where('document_type', 'progress_billing')->where('number', 'PB-TAX')->first();
        $this->assertNotNull($doc);

        $service->post($posted->refresh(), $user);
        $this->assertSame(1, Document::where('document_type', 'progress_billing')->where('number', 'PB-TAX')->count());

        $debit = $journal->entries->reduce(fn ($sum, $line) => bcadd($sum, $line->debit, 2), '0');
        $credit = $journal->entries->reduce(fn ($sum, $line) => bcadd($sum, $line->credit, 2), '0');
        $this->assertSame('1110.00', $debit);
        $this->assertSame($debit, $credit);

        $taxLine = $journal->entries->first(fn ($line) => (string) $line->credit === '110.00');
        $this->assertNotNull($taxLine);
        $this->assertSame($accounts['PPN_OUT']->id, $taxLine->account_id);

        $this->assertSame($journal->id, $service->post($posted->refresh(), $user)->journal_id);
    }

    public function test_customer_receipt_with_final_withholding_settles_ar_and_posts_balanced_journal(): void
    {
        [$project, $user, $company, $accounts, $billing, $bank] = $this->postedBillingFixture();

        $pphFinal = TaxRate::create(['company_id' => $company->id, 'code' => 'PPH42', 'name' => 'PPh Final 4(2) 2%', 'kind' => 'withholding', 'rate_percent' => '2']);
        $service = app(CashBankService::class);
        $receipt = $service->receiveCustomer($billing, $bank, '519.40', '2026-08-22', 'RCV-TAX', 'REF-1', 'rcv-tax-1', $user, ['tax_rate_id' => (string) $pphFinal->id, 'bukti_potong_number' => 'BP-2026-001', 'bukti_potong_date' => '2026-08-22']);

        $this->assertSame('10.38', $receipt->withholding_amount);
        $this->assertSame('BP-2026-001', $receipt->bukti_potong_number);

        $journal = $receipt->journal()->with('entries')->first();
        $debit = $journal->entries->reduce(fn ($sum, $line) => bcadd($sum, $line->debit, 2), '0');
        $credit = $journal->entries->reduce(fn ($sum, $line) => bcadd($sum, $line->credit, 2), '0');
        $this->assertSame($debit, $credit);
        $arCredit = $journal->entries->first(fn ($line) => $line->account_id === $accounts['AR']->id);
        $this->assertSame('529.78', $arCredit->credit);

        $service->receiveCustomer($billing, $bank, '530.22', '2026-08-23', 'RCV-TAX-2', 'REF-2', 'rcv-tax-2', $user);

        $aging = app(ReceivablePayableAgingService::class)->generate($company->id, now()->parse('2026-08-23'));
        $this->assertSame(0, $aging['receivables']->count());
    }

    public function test_receipt_settlement_cap_includes_withholding(): void
    {
        [, $user, , , $billing, $bank] = $this->postedBillingFixture();
        $service = app(CashBankService::class);

        $service->receiveCustomer($billing, $bank, '1000', '2026-08-22', 'RCV-1', 'REF-1', 'rcv-cap-1', $user);
        $this->expectException(ValidationException::class);
        $service->receiveCustomer($billing, $bank, '61', '2026-08-22', 'RCV-2', 'REF-2', 'rcv-cap-2', $user);
    }

    public function test_vendor_invoice_with_input_tax_matches_on_subtotal_and_posts_three_line_journal(): void
    {
        [$order, $user, $company, $accounts, $invoice] = $this->procurementFixture();

        $this->assertSame('matched', $invoice->match_status);
        $this->assertSame('500.00', $invoice->subtotal);
        $this->assertSame('55.00', $invoice->tax_amount);
        $this->assertSame('555.00', $invoice->total);

        $journal = app(ProcurementAccountingService::class)->postVendorInvoice($invoice, $user);
        $entries = $journal->entries()->get();
        $debit = $entries->reduce(fn ($sum, $line) => bcadd($sum, $line->debit, 2), '0');
        $credit = $entries->reduce(fn ($sum, $line) => bcadd($sum, $line->credit, 2), '0');
        $this->assertSame('555.00', $debit);
        $this->assertSame($debit, $credit);
        $taxDebit = $entries->first(fn ($line) => $line->account_id === $accounts['PPN_IN']->id);
        $this->assertSame('55.00', $taxDebit->debit);
    }

    public function test_vendor_payment_with_pph23_posts_balanced_journal_and_clears_ap(): void
    {
        [$order, $user, $company, $accounts, $invoice, $bank] = $this->postedProcurementFixture();

        $pph23 = TaxRate::create(['company_id' => $company->id, 'code' => 'PPH23', 'name' => 'PPh 23 2%', 'kind' => 'withholding', 'rate_percent' => '2']);
        $service = app(CashBankService::class);
        $payment = $service->payVendor($invoice, $bank, '277.00', '2026-08-22', 'PAY-TAX', 'REF-1', 'pay-tax-1', $user, ['tax_rate_id' => (string) $pph23->id, 'bukti_potong_number' => 'BP-V-001', 'bukti_potong_date' => '2026-08-22']);

        $this->assertSame('5.54', $payment->withholding_amount);
        $journal = $payment->journal()->with('entries')->first();
        $debit = $journal->entries->reduce(fn ($sum, $line) => bcadd($sum, $line->debit, 2), '0');
        $credit = $journal->entries->reduce(fn ($sum, $line) => bcadd($sum, $line->credit, 2), '0');
        $this->assertSame($debit, $credit);

        $service->payVendor($invoice, $bank, '272.46', '2026-08-23', 'PAY-TAX-2', 'REF-2', 'pay-tax-2', $user);

        $aging = app(ReceivablePayableAgingService::class)->generate($company->id, now()->parse('2026-08-23'));
        $this->assertSame(0, $aging['payables']->count());
    }

    public function test_payment_cap_rejects_when_cash_plus_withholding_exceeds_ap(): void
    {
        [, $user, , , $invoice, $bank] = $this->postedProcurementFixture();
        $pph23 = TaxRate::create(['company_id' => $invoice->company_id, 'code' => 'PPH23', 'name' => 'PPh 23 2%', 'kind' => 'withholding', 'rate_percent' => '2']);
        $service = app(CashBankService::class);

        $this->expectException(ValidationException::class);
        $service->payVendor($invoice, $bank, '546.00', '2026-08-22', 'PAY-OVER', 'REF-X', 'pay-over-1', $user, ['tax_rate_id' => (string) $pph23->id]);
    }

    private function approve(Company $company, User $user, ProgressBilling $billing): void
    {
        $workflow = ApprovalWorkflow::create(['company_id' => $company->id, 'name' => 'Billing', 'document_type' => 'progress_billing']);
        ApprovalRequest::create(['company_id' => $company->id, 'approval_workflow_id' => $workflow->id, 'approvable_type' => ProgressBilling::class, 'approvable_id' => $billing->id, 'submitted_by' => $user->id, 'status' => 'approved', 'idempotency_key' => 'approval-'.$billing->id, 'submitted_at' => now(), 'completed_at' => now()]);
    }

    private function baseCompany(): array
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $user = User::factory()->create();
        FiscalPeriod::create(['company_id' => $company->id, 'name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        NumberSequence::create(['company_id' => $company->id, 'document_type' => 'journal', 'prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);

        return [$company, $user];
    }

    private function billingFixture(): array
    {
        [$company, $user] = $this->baseCompany();
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C', 'name' => 'Client']);
        $project = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P', 'name' => 'Project', 'contract_value' => '100000', 'status' => 'active']);
        $accounts = [];
        foreach ([['AR', 'asset', 'debit'], ['RET', 'asset', 'debit'], ['ADV', 'liability', 'credit'], ['REV', 'revenue', 'credit'], ['PPN_OUT', 'liability', 'credit']] as [$code, $type, $normal]) {
            $accounts[$code] = Account::create(['company_id' => $company->id, 'code' => $code, 'name' => $code, 'type' => $type, 'normal_balance' => $normal]);
        }
        foreach ([['ar_debit', 'AR'], ['retention_debit', 'RET'], ['advance_debit', 'ADV'], ['revenue_credit', 'REV'], ['tax_credit', 'PPN_OUT']] as [$side, $code]) {
            AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'progress_billing', 'entry_side' => $side, 'account_id' => $accounts[$code]->id]);
        }

        return [$project, $user, $company, $accounts];
    }

    private function postedBillingFixture(): array
    {
        [$project, $user, $company, $accounts] = $this->billingFixture();
        $service = app(ProgressBillingService::class);
        $ppn = TaxRate::create(['company_id' => $company->id, 'code' => 'PPN11', 'name' => 'PPN 11%', 'kind' => 'ppn_output', 'rate_percent' => '11']);
        $billing = $service->create($project, ['number' => 'PB-TAX', 'billing_date' => '2026-08-21', 'progress_percent' => '10', 'gross_amount' => '1000', 'retention_percent' => '5', 'advance_recovery' => '0', 'tax_rate_id' => (string) $ppn->id, 'idempotency_key' => 'pb-tax'], $user);
        $this->approve($company, $user, $billing);
        $service->activateApproved($billing, $user);
        $service->post($billing->refresh(), $user);
        $bankAccount = Account::create(['company_id' => $company->id, 'code' => 'BANK', 'name' => 'Bank', 'type' => 'asset', 'normal_balance' => 'debit']);
        foreach ([['ar_credit', 'AR'], ['withholding_debit', 'PREPAID_PPH']] as [$side, $code]) {
            $account = $accounts[$code] ?? Account::create(['company_id' => $company->id, 'code' => $code, 'name' => $code, 'type' => 'asset', 'normal_balance' => 'debit']);
            $accounts[$code] = $account;
            AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'customer_receipt', 'entry_side' => $side, 'account_id' => $account->id]);
        }
        $bank = BankAccount::create(['company_id' => $company->id, 'account_id' => $bankAccount->id, 'code' => 'BCA', 'bank_name' => 'BCA', 'account_name' => 'GP', 'account_number' => '123']);

        return [$project, $user, $company, $accounts, $billing->refresh(), $bank];
    }

    private function procurementFixture(): array
    {
        [$company, $user] = $this->baseCompany();
        $vendor = Vendor::create(['company_id' => $company->id, 'code' => 'V1', 'name' => 'Supplier']);
        $item = Item::create(['company_id' => $company->id, 'sku' => 'ITM', 'name' => 'Besi', 'category' => 'Material', 'unit_id' => Unit::create(['company_id' => $company->id, 'code' => 'PCS', 'name' => 'Piece'])->id]);
        $order = PurchaseOrder::create(['company_id' => $company->id, 'vendor_id' => $vendor->id, 'number' => 'PO-1', 'status' => 'received', 'currency' => 'IDR', 'total' => '500', 'order_date' => '2026-08-20', 'created_by' => $user->id]);
        $order->items()->create(['item_id' => $item->id, 'quantity' => '10', 'unit_price' => '50', 'received_quantity' => '10']);
        $accounts = [];
        foreach ([['EXP', 'expense', 'debit'], ['AP', 'liability', 'credit'], ['PPN_IN', 'asset', 'debit']] as [$code, $type, $normal]) {
            $accounts[$code] = Account::create(['company_id' => $company->id, 'code' => $code, 'name' => $code, 'type' => $type, 'normal_balance' => $normal]);
        }
        foreach ([['debit', 'EXP'], ['credit', 'AP'], ['tax_debit', 'PPN_IN']] as [$side, $code]) {
            AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'vendor_invoice', 'entry_side' => $side, 'account_id' => $accounts[$code]->id]);
        }
        $ppn = TaxRate::create(['company_id' => $company->id, 'code' => 'PPNIN11', 'name' => 'PPN Masukan 11%', 'kind' => 'ppn_input', 'rate_percent' => '11']);
        $invoice = VendorInvoice::create(['company_id' => $company->id, 'vendor_id' => $vendor->id, 'purchase_order_id' => $order->id, 'number' => 'INV-1', 'invoice_date' => '2026-08-21', 'subtotal' => '500', 'tax_rate_id' => $ppn->id, 'tax_amount' => '55', 'total' => '555']);
        app(PurchaseOrderService::class)->match($invoice);

        return [$order, $user, $company, $accounts, $invoice->refresh()];
    }

    private function postedProcurementFixture(): array
    {
        [$order, $user, $company, $accounts, $invoice] = $this->procurementFixture();
        app(ProcurementAccountingService::class)->postVendorInvoice($invoice, $user);
        $bankAccount = Account::create(['company_id' => $company->id, 'code' => 'BANK', 'name' => 'Bank', 'type' => 'asset', 'normal_balance' => 'debit']);
        foreach ([['ap_debit', 'AP'], ['withholding_credit', 'PPH_PAYABLE']] as [$side, $code]) {
            $account = $accounts[$code] ?? Account::create(['company_id' => $company->id, 'code' => $code, 'name' => $code, 'type' => 'liability', 'normal_balance' => 'credit']);
            $accounts[$code] = $account;
            AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'vendor_payment', 'entry_side' => $side, 'account_id' => $account->id]);
        }
        $bank = BankAccount::create(['company_id' => $company->id, 'account_id' => $bankAccount->id, 'code' => 'BCA', 'bank_name' => 'BCA', 'account_name' => 'GP', 'account_number' => '123']);

        return [$order, $user, $company, $accounts, $invoice->refresh(), $bank];
    }
}
