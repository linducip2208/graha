<?php

namespace Tests\Feature\Accounting;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorInvoice;
use App\Services\ReceivablePayableAgingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReceivablePayableAgingTest extends TestCase
{
    use RefreshDatabase;

    public function test_posted_billing_is_bucketed_by_due_date_and_outstanding(): void
    {
        $company = Company::create(['code' => 'GP', 'name' => 'Graha Pondasi']);
        $user = User::factory()->create();
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C-1', 'name' => 'Client 1']);
        $project = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P-1', 'name' => 'Project 1', 'contract_value' => '1000', 'estimated_cost' => '500', 'status' => 'active']);
        ProgressBilling::create(['company_id' => $company->id, 'project_id' => $project->id, 'number' => 'B-1', 'billing_date' => '2026-07-01', 'progress_percent' => '50', 'gross_amount' => '100', 'retention_percent' => '0', 'retention_amount' => '0', 'advance_recovery' => '0', 'net_receivable' => '100', 'status' => 'posted', 'due_date' => '2026-07-15', 'created_by' => $user->id, 'idempotency_key' => 'b-1']);

        $report = app(ReceivablePayableAgingService::class)->generate($company->id, Carbon::parse('2026-08-21'));

        $this->assertSame('100.00', $report['ar_total']);
        $this->assertSame('100.00', $report['rows']->first()['outstanding']);
        $this->assertSame('31–60 hari', $report['rows']->first()['bucket']);
    }

    public function test_vendor_invoice_due_date_uses_configurable_company_term(): void
    {
        // ADR-027: termin utang vendor tidak lagi hardcoded 30 hari.
        $company = Company::create(['code' => 'GP', 'name' => 'Graha Pondasi']);
        CompanySetting::put($company->id, ['default_vendor_payment_term_days' => '45']);
        $user = User::factory()->create();
        $vendor = Vendor::create(['company_id' => $company->id, 'code' => 'V-1', 'name' => 'Supplier 1']);
        $invoice = VendorInvoice::create(['company_id' => $company->id, 'vendor_id' => $vendor->id, 'purchase_order_id' => PurchaseOrder::create(['company_id' => $company->id, 'vendor_id' => $vendor->id, 'number' => 'PO-AG-1', 'order_date' => '2026-07-01', 'created_by' => $user->id])->id, 'number' => 'VI-AG-1', 'invoice_date' => '2026-07-01', 'subtotal' => '500', 'tax_amount' => '0', 'total' => '500', 'match_status' => 'matched']);

        $report = app(ReceivablePayableAgingService::class)->generate($company->id, Carbon::parse('2026-08-10'));

        $this->assertSame('500.00', $report['ap_total']);
        $row = $report['rows']->firstWhere('number', $invoice->number);
        $this->assertNotNull($row);
        $this->assertSame('2026-08-15', $row['due_date']->toDateString(), 'Jatuh tempo = invoice_date + 45 hari sesuai setting perusahaan.');
        $this->assertSame('0–30 hari', $row['bucket']);
    }
}
