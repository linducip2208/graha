<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\Journal;
use App\Models\NumberSequence;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorInvoice;
use App\Services\AccountingService;
use App\Services\CashBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FxRealizedSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_receipt_on_foreign_billing_posts_balanced_fx_gain(): void
    {
        // Billing USD @10.000 → pelunasan saat kurs 11.000 = realized gain.
        [$company, $user, $bank, $billing] = $this->fixture();
        DB::table('fx_rates')->insert(['company_id' => $company->id, 'currency' => 'USD', 'effective_date' => '2026-08-01', 'rate_to_idr' => '10000', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('fx_rates')->insert(['company_id' => $company->id, 'currency' => 'USD', 'effective_date' => '2026-08-21', 'rate_to_idr' => '11000', 'created_at' => now(), 'updated_at' => now()]);

        $service = app(CashBankService::class);
        $receipt = $service->receiveCustomer($billing, $bank, '550000.00', '2026-08-21', 'RC-FX-1', 'TT', 'fx-receipt-1', $user);

        // Implied foreign 50 USD; nilai kurs dokumen 500.000 → gain 50.000.
        $this->assertSame('50000.00', (string) $receipt->fx_difference);
        $this->assertBalanced((int) $receipt->journal_id);
        $this->assertDatabaseHas('journal_entries', ['journal_id' => $receipt->journal_id, 'credit' => '50000.00']);

        // Idempoten: kunci sama mengembalikan receipt yang sama.
        $duplicate = $service->receiveCustomer($billing, $bank, '550000.00', '2026-08-21', 'RC-FX-1', 'TT', 'fx-receipt-1', $user);
        $this->assertSame($receipt->id, $duplicate->id);
    }

    public function test_vendor_payment_on_foreign_invoice_posts_balanced_fx_loss(): void
    {
        // Invoice USD @10.000 → dibayar saat kurs 12.500 = realized loss.
        [$company, $user, $bank, $invoice] = $this->fixture();
        DB::table('fx_rates')->insert(['company_id' => $company->id, 'currency' => 'USD', 'effective_date' => '2026-08-01', 'rate_to_idr' => '10000', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('fx_rates')->insert(['company_id' => $company->id, 'currency' => 'USD', 'effective_date' => '2026-08-22', 'rate_to_idr' => '12500', 'created_at' => now(), 'updated_at' => now()]);
        $vendor = Vendor::create(['company_id' => $company->id, 'code' => 'V-FX', 'name' => 'Vendor FX']);
        $order = PurchaseOrder::create(['company_id' => $company->id, 'vendor_id' => $vendor->id, 'number' => 'PO-FX-1', 'order_date' => '2026-07-15', 'currency' => 'USD', 'total' => '1000000', 'created_by' => $user->id]);
        $invoice = VendorInvoice::create([
            'company_id' => $company->id, 'vendor_id' => $vendor->id,
            'purchase_order_id' => $order->id, 'number' => 'VI-FX-1',
            'invoice_date' => '2026-08-01', 'subtotal' => '1000000', 'tax_amount' => '0', 'total' => '1000000',
            'match_status' => 'matched', 'currency' => 'USD', 'exchange_rate' => '10000',
        ]);
        $this->postApJournal($company->id, $invoice->id, $user);

        $service = app(CashBankService::class);
        $payment = $service->payVendor($invoice, $bank, '550000.00', '2026-08-22', 'PV-FX-1', 'TT', 'fx-payment-1', $user);

        // Implied foreign 44 USD; nilai kurs dokumen 440.000 → loss 110.000,
        // AP dilepas 440.000 sehingga jurnal tetap seimbang.
        $this->assertSame('110000.00', (string) $payment->fx_difference);
        $this->assertBalanced((int) $payment->journal_id);
        $this->assertDatabaseHas('journal_entries', ['journal_id' => $payment->journal_id, 'debit' => '110000.00']);
    }

    private function assertBalanced(int $journalId): void
    {
        $totals = DB::table('journal_entries')->where('journal_id', $journalId)->selectRaw('SUM(debit) as d, SUM(credit) as c')->first();
        $this->assertSame((float) $totals->d, (float) $totals->c, 'Jurnal FX tidak seimbang.');
        $this->assertGreaterThan(0, (float) $totals->d);
    }

    /** Jurnal AP posted manual agar gate payVendor lolos tanpa alur PO penuh. */
    private function postApJournal(int $companyId, int $invoiceId, User $user): void
    {
        $ap = Account::where('company_id', $companyId)->where('code', 'AP')->firstOrFail();
        $grni = Account::where('company_id', $companyId)->where('code', 'GRNI')->firstOrFail();
        app(AccountingService::class)->post($companyId, '2026-08-01', 'vendor_invoice', (string) $invoiceId, 'Posting invoice uji', [
            ['account_id' => $ap->id, 'debit' => '1000000.00', 'credit' => '0'],
            ['account_id' => $grni->id, 'debit' => '0', 'credit' => '1000000.00'],
        ], 'fx-invoice-posting:'.$invoiceId, $user);
        $this->assertSame('posted', Journal::where('source_type', 'vendor_invoice')->where('source_id', (string) $invoiceId)->value('status'));
    }

    private function fixture(): array
    {
        $company = Company::create(['code' => 'GP', 'name' => 'Graha Pondasi']);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        FiscalPeriod::create(['company_id' => $company->id, 'name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        NumberSequence::create(['company_id' => $company->id, 'document_type' => 'journal', 'prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);

        $bankGl = Account::create(['company_id' => $company->id, 'code' => 'BANK', 'name' => 'Bank', 'type' => 'asset', 'normal_balance' => 'debit']);
        $ar = Account::create(['company_id' => $company->id, 'code' => 'AR', 'name' => 'Piutang', 'type' => 'asset', 'normal_balance' => 'debit']);
        Account::create(['company_id' => $company->id, 'code' => 'AP', 'name' => 'Utang Usaha', 'type' => 'liability', 'normal_balance' => 'credit']);
        Account::create(['company_id' => $company->id, 'code' => 'GRNI', 'name' => 'GRNI', 'type' => 'liability', 'normal_balance' => 'credit']);
        $fxGain = Account::create(['company_id' => $company->id, 'code' => 'FXGAIN', 'name' => 'Laba Selisih Kurs', 'type' => 'revenue', 'normal_balance' => 'credit']);
        $fxLoss = Account::create(['company_id' => $company->id, 'code' => 'FXLOSS', 'name' => 'Rugi Selisih Kurs', 'type' => 'expense', 'normal_balance' => 'debit']);
        foreach (['customer_receipt', 'vendor_payment'] as $event) {
            AccountingMapping::create(['company_id' => $company->id, 'event_type' => $event, 'entry_side' => 'fx_gain_credit', 'account_id' => $fxGain->id]);
            AccountingMapping::create(['company_id' => $company->id, 'event_type' => $event, 'entry_side' => 'fx_loss_debit', 'account_id' => $fxLoss->id]);
        }
        AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'customer_receipt', 'entry_side' => 'ar_credit', 'account_id' => $ar->id]);
        AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'vendor_payment', 'entry_side' => 'ap_debit', 'account_id' => Account::where('company_id', $company->id)->where('code', 'AP')->firstOrFail()->id]);
        $bank = BankAccount::create(['company_id' => $company->id, 'account_id' => $bankGl->id, 'code' => 'BCA', 'bank_name' => 'Bank', 'account_name' => 'PT GP', 'account_number' => '001', 'currency' => 'IDR']);

        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C-FX', 'name' => 'Pelanggan FX']);
        $project = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P-FX', 'name' => 'Proyek FX', 'contract_value' => '2000000', 'status' => 'active']);
        $billing = ProgressBilling::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'number' => 'PB-FX-1',
            'billing_date' => '2026-08-01', 'progress_percent' => '100', 'gross_amount' => '1000000',
            'retention_percent' => '0', 'retention_amount' => '0', 'advance_recovery' => '0',
            'net_receivable' => '1000000', 'status' => 'posted', 'created_by' => $user->id,
            'idempotency_key' => 'fx-billing-1', 'currency' => 'USD', 'exchange_rate' => '10000',
        ]);

        return [$company, $user, $bank, $billing];
    }
}
