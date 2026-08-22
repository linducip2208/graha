<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentMeterLog;
use App\Models\FiscalPeriod;
use App\Models\GoodsReceipt;
use App\Models\HseIncident;
use App\Models\Item;
use App\Models\JobSafetyAnalysis;
use App\Models\Nonconformity;
use App\Models\NumberSequence;
use App\Models\Permission;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\ProjectDailyReport;
use App\Models\PurchaseOrder;
use App\Models\RiskOpportunity;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorInvoice;
use App\Models\Warehouse;
use App\Services\ApprovalEngine;
use App\Services\BoredPileService;
use App\Services\CashBankService;
use App\Services\EquipmentService;
use App\Services\InventoryService;
use App\Services\ProcurementAccountingService;
use App\Services\ProgressBillingService;
use App\Services\PurchaseOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(['code' => 'GP'], ['name' => 'PT Graha Pondasi']);
        $admin = User::firstOrCreate(['email' => 'admin@grahapondasi.test'], ['name' => 'Super Admin', 'password' => 'password']);
        $company->users()->syncWithoutDetaching([$admin->id => ['is_default' => true, 'is_active' => true]]);

        $this->line('Organisasi, COA, mapping, tarif pajak...');
        $users = $this->seedUsers($company);
        $accounts = $this->seedAccounts($company);
        $this->seedMappings($company, $accounts);
        $this->seedTaxRates($company);
        $this->seedPeriodsAndSequences($company);
        $customer = $this->seedPartners($company);

        $this->line('Proyek & bored pile...');
        $project = $this->seedProject($company, $customer, $admin);
        [$warehouse, $bins] = $this->seedInventory($company, $users['procurement']);

        $this->line('Alur procurement: PO -> receipt -> invoice PPN -> pembayaran + PPh 23...');
        [$invoice, $bank] = $this->seedProcurementFlow($company, $users, $accounts, $warehouse, $bins);

        $this->line('Alur billing: billing PPN 11% -> approval -> posting AR -> penerimaan dengan bukti potong PPh final...');
        $this->seedBillingFlow($company, $users, $project, $bank);

        $this->line('Equipment, QMS, HSE...');
        $this->seedOperationalSamples($company, $users['pm'], $project);

        $this->line('Selesai. Login: admin@grahapondasi.test / password (finance@, pm@, procurement@, direktur@ sama).');
    }

    private function line(string $message): void
    {
        $this->command?->info($message);
    }

    private function seedUsers(Company $company): array
    {
        $definitions = [
            'finance@grahapondasi.test' => ['Finance Manager', 'finance-manager', ['finance.view', 'finance.manage', 'accounting.post', 'report.view', 'report.export', 'approval.view', 'approval.decide']],
            'pm@grahapondasi.test' => ['Project Manager', 'project-manager', ['project.view', 'project.manage', 'inventory.view', 'equipment.view', 'equipment.manage', 'hse.view', 'report.view']],
            'procurement@grahapondasi.test' => ['Procurement Officer', 'procurement-officer', ['procurement.view', 'procurement.manage', 'inventory.view', 'inventory.manage']],
            'direktur@grahapondasi.test' => ['Direktur Operasi', 'director', ['approval.view', 'approval.decide', 'approval.manage', 'tender.view', 'tender.manage', 'project.view', 'finance.view', 'report.view']],
        ];
        $users = [];
        foreach ($definitions as $email => [$name, $roleCode, $permissions]) {
            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'password']);
            $company->users()->syncWithoutDetaching([$user->id => ['is_default' => true, 'is_active' => true]]);
            $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => $roleCode], ['name' => $name, 'is_system' => false]);
            foreach ($permissions as $code) {
                $permission = Permission::firstOrCreate(['code' => $code], ['name' => str($code)->replace('.', ' ')->title(), 'module' => str($code)->before('.')]);
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
            $pivotId = DB::table('company_user')->where('company_id', $company->id)->where('user_id', $user->id)->value('id');
            if ($pivotId) {
                DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $pivotId, 'role_id' => $role->id]);
            }
            $key = match (true) {
                str_contains($email, 'finance') => 'finance',
                str_contains($email, 'pm') => 'pm',
                str_contains($email, 'procurement') => 'procurement',
                default => 'director',
            };
            $users[$key] = $user;
        }

        return $users;
    }

    private function seedAccounts(Company $company): array
    {
        $blueprint = [
            ['1-1000', 'Kas & Bank', 'asset', 'debit'],
            ['1-1100', 'Piutang Retensi', 'asset', 'debit'],
            ['1-1200', 'Pajak Masukan (PPN)', 'asset', 'debit'],
            ['1-1300', 'Pajak Dibayar di Muka (PPh)', 'asset', 'debit'],
            ['1-2000', 'Material Gudang', 'asset', 'debit'],
            ['2-1000', 'Uang Muka Pelanggan', 'liability', 'credit'],
            ['2-2000', 'GRNI', 'liability', 'credit'],
            ['2-2100', 'Utang Usaha (AP)', 'liability', 'credit'],
            ['2-2200', 'PPN Keluaran', 'liability', 'credit'],
            ['2-2300', 'Hutang PPh Dipotong', 'liability', 'credit'],
            ['4-1000', 'Pendapatan Kontrak (Revenue)', 'revenue', 'credit'],
            ['5-2000', 'Biaya Material Proyek', 'expense', 'debit'],
        ];
        $accounts = [];
        foreach ($blueprint as [$code, $name, $type, $normal]) {
            $accounts[$code] = Account::firstOrCreate(['company_id' => $company->id, 'code' => $code], ['name' => $name, 'type' => $type, 'normal_balance' => $normal]);
        }

        return $accounts;
    }

    private function seedMappings(Company $company, array $accounts): void
    {
        $matrix = [
            ['progress_billing', 'ar_debit', '1-1000'],
            ['progress_billing', 'retention_debit', '1-1100'],
            ['progress_billing', 'advance_debit', '2-1000'],
            ['progress_billing', 'revenue_credit', '4-1000'],
            ['progress_billing', 'tax_credit', '2-2200'],
            ['customer_receipt', 'ar_credit', '1-1000'],
            ['customer_receipt', 'withholding_debit', '1-1300'],
            ['goods_receipt', 'debit', '1-2000'],
            ['goods_receipt', 'credit', '2-2000'],
            ['vendor_invoice', 'debit', '5-2000'],
            ['vendor_invoice', 'credit', '2-2100'],
            ['vendor_invoice', 'tax_debit', '1-1200'],
            ['vendor_payment', 'ap_debit', '2-2100'],
            ['vendor_payment', 'withholding_credit', '2-2300'],
        ];
        foreach ($matrix as [$event, $side, $code]) {
            AccountingMapping::firstOrCreate(['company_id' => $company->id, 'event_type' => $event, 'entry_side' => $side], ['account_id' => $accounts[$code]->id]);
        }
    }

    private function seedTaxRates(Company $company): void
    {
        foreach ([
            ['PPN-KELUARAN', 'PPN Keluaran 11%', 'ppn_output', '11'],
            ['PPN-MASUKAN', 'PPN Masukan 11%', 'ppn_input', '11'],
            ['PPH-FINAL', 'PPh Final Konstruksi 4(2) 1.5%', 'withholding', '1.5'],
            ['PPH23', 'PPh Pasal 23 2%', 'withholding', '2'],
        ] as [$code, $name, $kind, $percent]) {
            TaxRate::firstOrCreate(['company_id' => $company->id, 'code' => $code], ['name' => $name, 'kind' => $kind, 'rate_percent' => $percent]);
        }
    }

    private function seedPeriodsAndSequences(Company $company): void
    {
        FiscalPeriod::firstOrCreate(['company_id' => $company->id, 'name' => 'FY 2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        NumberSequence::firstOrCreate(['company_id' => $company->id, 'document_type' => 'journal'], ['prefix' => 'JV', 'padding' => 5, 'last_reset_year' => 2026]);
    }

    private function seedPartners(Company $company): Customer
    {
        Customer::firstOrCreate(['company_id' => $company->id, 'code' => 'CUST-001'], ['name' => 'PT Wijaya Karya Bangunan']);
        Vendor::firstOrCreate(['company_id' => $company->id, 'code' => 'VEND-001'], ['name' => 'CV Besi Baja Nusantara', 'tax_number' => '01.234.567.8-999.000']);
        Vendor::firstOrCreate(['company_id' => $company->id, 'code' => 'VEND-002'], ['name' => 'PT Beton Ready Mix Sejahtera', 'tax_number' => '02.345.678.9-051.000']);

        return Customer::where('company_id', $company->id)->where('code', 'CUST-001')->first();
    }

    private function seedProject(Company $company, Customer $customer, User $actor): Project
    {
        $project = Project::firstOrCreate(['company_id' => $company->id, 'code' => 'PRJ-2601'], [
            'customer_id' => $customer->id,
            'name' => 'Fondasi Bored Pile Tower Transmisi Cikarang',
            'contract_value' => '2500000000',
            'estimated_cost' => '1900000000',
            'planned_start' => '2026-06-01',
            'planned_end' => '2026-12-20',
            'overbreak_tolerance_percent' => '8',
            'status' => 'in_progress',
        ]);
        $zones = collect([
            'Z1' => $project->zones()->firstOrCreate(['code' => 'Z1'], ['name' => 'Zona Menara 1-6']),
            'Z2' => $project->zones()->firstOrCreate(['code' => 'Z2'], ['name' => 'Zona Menara 7-12']),
        ]);
        $wbsId = DB::table('project_wbs')->where('project_id', $project->id)->where('code', 'WBS-01')->value('id')
            ?? tap(DB::table('project_wbs')->insertGetId(['project_id' => $project->id, 'code' => 'WBS-01', 'name' => 'Pekerjaan Pondasi', 'budget' => '1900000000']), fn () => null);
        $costCodeId = DB::table('project_cost_codes')->where('project_id', $project->id)->where('code', 'CC-MAT')->value('id')
            ?? DB::table('project_cost_codes')->insertGetId(['project_id' => $project->id, 'code' => 'CC-MAT', 'name' => 'Material Pondasi', 'category' => 'material']);

        $piles = [
            ['BP-001', 'Z1', 'completed', '800', '22.500'],
            ['BP-002', 'Z1', 'completed', '800', '21.800'],
            ['BP-003', 'Z1', 'testing', '1000', '24.000'],
            ['BP-004', 'Z2', 'drilling', '1000', '25.000'],
            ['BP-005', 'Z2', 'cage_installation', '1200', '26.500'],
            ['BP-006', 'Z2', 'planned', '800', '20.000'],
            ['BP-007', 'Z2', 'planned', '1000', '24.500'],
            ['BP-008', 'Z2', 'planned', '1000', '24.000'],
        ];
        $chain = ['planned', 'setting_out', 'drilling', 'cleaning', 'inspection', 'cage_installation', 'casting', 'testing', 'completed'];
        $pileService = app(BoredPileService::class);
        foreach ($piles as [$pileNumber, $zone, $status, $diameter, $depth]) {
            $pile = $project->boredPiles()->firstOrCreate(['pile_number' => $pileNumber], [
                'project_zone_id' => $zones[$zone]->id,
                'project_wbs_id' => $wbsId,
                'project_cost_code_id' => $costCodeId,
                'diameter_mm' => $diameter,
                'planned_depth_m' => $depth,
                'status' => 'planned',
                'created_by' => $actor->id,
            ]);
            if ($status !== 'planned') {
                foreach (array_slice($chain, 1, array_search($status, $chain, true)) as $step) {
                    if ($pile->refresh()->status !== $step) {
                        $pileService->transition($pile, $step, $actor, 'Seed demo: progres awal.');
                    }
                }
            }
        }
        foreach ($project->boredPiles()->whereIn('status', ['completed', 'testing'])->get() as $pile) {
            app(BoredPileService::class)->recordConcrete(
                $pile,
                (string) ($pile->planned_depth_m - rand(0, 30) / 100),
                number_format(M_PI * pow((float) $pile->diameter_mm / 2000, 2) * (float) $pile->planned_depth_m * (1 + rand(10, 60) / 1000), 4, '.', ''),
                $actor
            );
        }

        ProjectDailyReport::firstOrCreate(['project_id' => $project->id, 'report_date' => now()->subDay()->toDateString()], [
            'weather' => 'Cerah',
            'manpower_count' => 18,
            'work_summary' => 'Drilling BP-004 mencapai 14 m, cage installation BP-005 selesai dipasang, persiapan casting BP-003.',
            'issues' => 'Hujan singkat sore hari menghentikan operasi 45 menit.',
            'prepared_by' => $actor->id,
        ]);

        return $project;
    }

    private function seedInventory(Company $company, User $actor): array
    {
        $ton = Unit::firstOrCreate(['company_id' => $company->id, 'code' => 'TON'], ['name' => 'Ton']);
        $sak = Unit::firstOrCreate(['company_id' => $company->id, 'code' => 'SAK'], ['name' => 'Sak']);
        $warehouse = Warehouse::firstOrCreate(['company_id' => $company->id, 'code' => 'WH-JKT'], ['name' => 'Gudang Jakarta Timur']);
        $bins = collect([
            'A1' => $warehouse->bins()->firstOrCreate(['code' => 'A1'], ['name' => 'Rak Besi']),
            'B1' => $warehouse->bins()->firstOrCreate(['code' => 'B1'], ['name' => 'Area Bentonite']),
        ]);

        foreach ([['ITM-BESI', 'Besi Tulangan D16', $ton->id, 'A1', '14500000'], ['ITM-BENTONITE', 'Bentonite Drilling Grade', $sak->id, 'B1', '85000']] as [$sku, $name, $unitId, $binCode, $cost]) {
            $item = Item::firstOrCreate(['company_id' => $company->id, 'sku' => $sku], ['name' => $name, 'category' => 'Material', 'unit_id' => $unitId]);
            if (! StockBalance::where('company_id', $company->id)->where('item_id', $item->id)->exists()) {
                app(InventoryService::class)->post([
                    'company_id' => $company->id, 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'warehouse_bin_id' => $bins[$binCode]->id,
                ], 'adjustment_in', '50', 'demo-stock:'.$sku, $actor, ['type' => 'opening_balance', 'id' => $sku], $cost);
            }
        }

        return [$warehouse, $bins];
    }

    private function seedProcurementFlow(Company $company, array $users, array $accounts, Warehouse $warehouse, $bins): array
    {
        $vendor = Vendor::where('company_id', $company->id)->where('code', 'VEND-001')->first();
        $item = Item::where('company_id', $company->id)->where('sku', 'ITM-BESI')->first();

        $orderService = app(PurchaseOrderService::class);
        $order = PurchaseOrder::firstOrCreate(['company_id' => $company->id, 'number' => 'PO-DEMO-001'], [
            'vendor_id' => $vendor->id, 'order_date' => now()->subDays(10)->toDateString(), 'currency' => 'IDR', 'created_by' => $users['procurement']->id, 'status' => 'draft',
        ]);
        if ($order->items()->count() === 0) {
            $order->items()->create(['item_id' => $item->id, 'quantity' => '5', 'unit_price' => '15000000']);
            $orderService->recalculate($order);
        }

        if ($order->status === 'draft') {
            $workflow = ApprovalWorkflow::firstOrCreate(['company_id' => $company->id, 'name' => 'Approval PO Umum', 'document_type' => 'purchase_order']);
            ApprovalStep::firstOrCreate(['approval_workflow_id' => $workflow->id, 'sequence' => 1], ['action' => 'approve', 'mode' => 'any', 'role_id' => Role::where('company_id', $company->id)->where('code', 'director')->firstOrFail()->id, 'sla_hours' => 24]);
            ApprovalRequest::firstOrCreate(['company_id' => $company->id, 'idempotency_key' => 'demo-po-approval'], [
                'approval_workflow_id' => $workflow->id, 'approvable_type' => PurchaseOrder::class, 'approvable_id' => $order->id,
                'submitted_by' => $users['procurement']->id, 'status' => 'approved', 'current_sequence' => 1, 'submitted_at' => now(), 'completed_at' => now(),
            ]);
            $orderService->activateApproved($order, $users['director']);
        }

        if (! GoodsReceipt::where('purchase_order_id', $order->id)->exists() && in_array($order->fresh()->status, ['approved', 'issued', 'partially_received'], true)) {
            $orderService->receive($order->fresh(), $warehouse->id, [[
                'purchase_order_item_id' => $order->items()->first()->id,
                'warehouse_bin_id' => $bins['A1']->id,
                'quantity' => '5',
            ]], 'GR-DEMO-001', 'demo-gr-001', $users['procurement']);
        }

        $invoice = VendorInvoice::where('company_id', $company->id)->where('number', 'INV-BESI-88')->first();
        if (! $invoice) {
            $order->refresh();
            $subtotal = (string) $order->total;
            $ppnIn = TaxRate::where('company_id', $company->id)->where('code', 'PPN-MASUKAN')->first();
            $taxAmount = bcdiv(bcmul($subtotal, (string) $ppnIn->rate_percent, 4), '100', 2);
            $invoice = VendorInvoice::create([
                'company_id' => $company->id, 'vendor_id' => $vendor->id, 'purchase_order_id' => $order->id,
                'number' => 'INV-BESI-88', 'invoice_date' => now()->subDays(6)->toDateString(),
                'subtotal' => $subtotal, 'tax_rate_id' => $ppnIn->id, 'tax_amount' => $taxAmount, 'total' => bcadd($subtotal, $taxAmount, 2),
            ]);
            app(PurchaseOrderService::class)->match($invoice);
        }

        $receipt = GoodsReceipt::where('purchase_order_id', $order->id)->first();
        if ($receipt && ! DB::table('journals')->where('company_id', $company->id)->where('source_type', 'goods_receipt')->where('source_id', (string) $receipt->id)->exists()) {
            app(ProcurementAccountingService::class)->postGoodsReceipt($receipt, $users['finance']);
        }
        if (! DB::table('journals')->where('company_id', $company->id)->where('source_type', 'vendor_invoice')->where('source_id', (string) $invoice->id)->exists()) {
            app(ProcurementAccountingService::class)->postVendorInvoice($invoice->refresh(), $users['finance']);
        }

        $bank = BankAccount::firstOrCreate(['company_id' => $company->id, 'code' => 'BCA-OPS'], [
            'account_id' => $accounts['1-1000']->id, 'bank_name' => 'BCA', 'account_name' => 'PT Graha Pondasi Operasional', 'account_number' => '5410123456', 'currency' => 'IDR',
        ]);

        if (! $invoice->vendorPayments()->exists()) {
            $pph23 = TaxRate::where('company_id', $company->id)->where('code', 'PPH23')->first();
            $service = app(CashBankService::class);
            $service->payVendor($invoice->refresh(), $bank, '41625000', now()->subDays(3)->toDateString(), 'PAY-DEMO-001', 'TRF-99182X', 'demo-pay-001', $users['finance'], [
                'tax_rate_id' => (string) $pph23->id, 'bukti_potong_number' => 'BP-23/'.now()->format('Y').'/001', 'bukti_potong_date' => now()->subDays(3)->toDateString(),
            ]);
            $service->payVendor($invoice->refresh(), $bank, '40792500', now()->subDays(2)->toDateString(), 'PAY-DEMO-002', 'TRF-99183X', 'demo-pay-002', $users['finance']);
        }

        return [$invoice->refresh(), $bank];
    }

    private function seedBillingFlow(Company $company, array $users, Project $project, BankAccount $bank): void
    {
        $service = app(ProgressBillingService::class);
        $engine = app(ApprovalEngine::class);
        $ppnOut = TaxRate::where('company_id', $company->id)->where('code', 'PPN-KELUARAN')->first();
        $pphFinal = TaxRate::where('company_id', $company->id)->where('code', 'PPH-FINAL')->first();

        $billingOne = ProgressBilling::where('company_id', $company->id)->where('number', 'PB-DEMO-001')->first();
        if (! $billingOne) {
            $billingOne = $service->create($project, [
                'number' => 'PB-DEMO-001', 'billing_date' => now()->subDays(15)->toDateString(), 'due_date' => now()->addDays(15)->toDateString(),
                'progress_percent' => '20', 'gross_amount' => '500000000', 'retention_percent' => '5', 'advance_recovery' => '0',
                'tax_rate_id' => (string) $ppnOut->id, 'idempotency_key' => 'demo-billing-1',
            ], $users['finance']);
            $workflow = ApprovalWorkflow::firstOrCreate(['company_id' => $company->id, 'name' => 'Approval Billing', 'document_type' => 'progress_billing']);
            ApprovalStep::firstOrCreate(['approval_workflow_id' => $workflow->id, 'sequence' => 1], ['action' => 'approve', 'mode' => 'any', 'role_id' => Role::where('company_id', $company->id)->where('code', 'director')->firstOrFail()->id, 'sla_hours' => 48]);
            ApprovalRequest::firstOrCreate(['company_id' => $company->id, 'idempotency_key' => 'demo-billing-1-approval'], [
                'approval_workflow_id' => $workflow->id, 'approvable_type' => ProgressBilling::class, 'approvable_id' => $billingOne->id,
                'submitted_by' => $users['finance']->id, 'status' => 'approved', 'current_sequence' => 1, 'amount' => '500000000', 'currency' => 'IDR', 'submitted_at' => now(), 'completed_at' => now(),
            ]);
            $service->activateApproved($billingOne, $users['director']);
            $service->post($billingOne->refresh(), $users['finance']);
            $billingOne = $billingOne->refresh();

            app(CashBankService::class)->receiveCustomer($billingOne, $bank, '257400000', now()->subDays(4)->toDateString(), 'RCV-DEMO-001', 'TRF-IN-77231', 'demo-rcv-001', $users['finance'], [
                'tax_rate_id' => (string) $pphFinal->id, 'bukti_potong_number' => 'BP-42/'.now()->format('Y').'/WKBM', 'bukti_potong_date' => now()->subDays(4)->toDateString(),
            ]);
            app(CashBankService::class)->receiveCustomer($billingOne->refresh(), $bank, '268739000', now()->subDays(2)->toDateString(), 'RCV-DEMO-002', 'TRF-IN-77240', 'demo-rcv-002', $users['finance']);

            foreach ([['TRF-IN-77231', now()->subDays(4)->toDateString(), '257400000', 'Transfer masuk klien WKBM'], ['TRF-IN-77240', now()->subDays(2)->toDateString(), '268739000', 'Transfer masuk klien WKBM'], ['TRF-99182X', now()->subDays(3)->toDateString(), '-41625000', 'Transfer keluar vendor besi']] as [$reference, $date, $amount, $description]) {
                BankStatementLine::firstOrCreate(['bank_account_id' => $bank->id, 'reference' => $reference], [
                    'transaction_date' => $date, 'description' => $description, 'amount' => $amount,
                ]);
            }
        }

        $billingTwo = ProgressBilling::where('company_id', $company->id)->where('number', 'PB-DEMO-002')->first();
        if (! $billingTwo) {
            $billingTwo = $service->create($project, [
                'number' => 'PB-DEMO-002', 'billing_date' => now()->subDays(2)->toDateString(), 'due_date' => now()->addDays(28)->toDateString(),
                'progress_percent' => '35', 'gross_amount' => '375000000', 'retention_percent' => '5', 'advance_recovery' => '25000000',
                'tax_rate_id' => (string) $ppnOut->id, 'idempotency_key' => 'demo-billing-2',
            ], $users['finance']);
            $workflow = ApprovalWorkflow::where('company_id', $company->id)->where('name', 'Approval Billing')->where('document_type', 'progress_billing')->firstOrFail();
            app(ApprovalEngine::class)->submit($workflow, $billingTwo, $users['finance'], 'demo-billing-2-submit');
            $billingTwo->update(['status' => 'pending_approval']);
        }
    }

    private function seedOperationalSamples(Company $company, User $pm, Project $project): void
    {
        $rig = Equipment::firstOrCreate(['company_id' => $company->id, 'code' => 'EQ-RIG-01'], [
            'name' => 'Bored Pile Rig SOILMEC R-516', 'ownership' => 'owned', 'category' => 'rig', 'current_hour_meter' => '12480', 'fuel_target_lph' => '18.5', 'status' => 'operational',
        ]);
        Equipment::firstOrCreate(['company_id' => $company->id, 'code' => 'EQ-EXC-01'], [
            'name' => 'Excavator CAT 320', 'ownership' => 'owned', 'category' => 'excavator', 'current_hour_meter' => '8320', 'status' => 'maintenance',
        ]);
        if (! EquipmentMeterLog::where('equipment_id', $rig->id)->exists()) {
            app(EquipmentService::class)->recordMeter($rig, '12480', $pm);
        }

        RiskOpportunity::firstOrCreate(['company_id' => $company->id, 'code' => 'RO-001'], [
            'project_id' => $project->id, 'owner_id' => $pm->id, 'type' => 'risk', 'title' => 'Overbreak melebihi toleransi pada lapisan pasir',
            'description' => 'Overbreak terukur hingga 12% pada zona pasir lepas; berpotensi pembengkakan volume beton.',
            'likelihood' => 3, 'impact' => 4, 'inherent_score' => 12,
            'controls' => 'Monitoring slurry, casing ekstra pada segmen pasir, verifikasi volume tiap casting.',
            'residual_likelihood' => 2, 'residual_impact' => 3, 'residual_score' => 6, 'status' => 'open',
        ]);
        Nonconformity::firstOrCreate(['company_id' => $company->id, 'number' => 'NCR-2026-001'], [
            'source_type' => 'inspection', 'severity' => 'major',
            'description' => 'Overbreak BP-002 terukur 12.3%, di atas toleransi kontrak 8%. Perlu evaluasi metode casing dan kepadatan slurry.',
            'reported_by' => $pm->id, 'due_at' => now()->addDays(14)->toDateString(), 'status' => 'open',
        ]);
        JobSafetyAnalysis::firstOrCreate(['company_id' => $company->id, 'number' => 'JSA-2026-001'], [
            'project_id' => $project->id, 'activity' => 'Drilling, pemasangan cage, tremie concreting',
            'location' => 'Site Cikarang - Zona Z1/Z2', 'hazards' => ['Lubang bor runtuh', 'Tertimpa casing', 'Terjangan beton saat tremie'],
            'controls' => ['Slurry bentonite sesuai viskositas', 'Barricade zona drilling', 'PPE lengkap', 'Komunikasi radio antar operator'],
            'risk_level' => 'high', 'status' => 'active', 'valid_from' => now()->subDays(20)->toDateString(), 'valid_until' => now()->addDays(70)->toDateString(), 'prepared_by' => $pm->id,
        ]);
        HseIncident::firstOrCreate(['company_id' => $company->id, 'number' => 'INC-2026-001'], [
            'project_id' => $project->id, 'type' => 'near_miss', 'severity' => 'minor',
            'occurred_at' => now()->subDays(5), 'location' => 'Yard rig BP-004',
            'description' => 'Sling bergeser saat pemindahan casing; tidak ada korban dan tidak ada kerusakan.',
            'immediate_action' => 'Pekerjaan crane dihentikan, inspeksi sling semua unit.', 'status' => 'investigating', 'reported_by' => $pm->id,
        ]);
    }
}
