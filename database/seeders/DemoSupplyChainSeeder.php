<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\BankAccount;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeLog;
use App\Models\EquipmentMeterLog;
use App\Models\FuelUsage;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\Vendor;
use App\Models\VendorInvoice;
use App\Models\Warehouse;
use App\Services\CashBankService;
use App\Services\EquipmentService;
use App\Services\InventoryService;
use App\Services\ProcurementAccountingService;
use App\Services\PurchaseOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo supply chain (ADR-079): gudang+item+stok awal, alur procurement
 * lengkap (PO→GR→invoice→payment), 6 equipment dengan meter/BBM/downtime.
 * Semua idempotent via stable keys; nilai deterministik tanpa rand().
 */
class DemoSupplyChainSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = DemoDataSeeder::company()->id;
        $procurement = DemoDataSeeder::user('procurement@grahapondasi.test');
        $warehouseUser = DemoDataSeeder::user('warehouse@grahapondasi.test');
        $finance = DemoDataSeeder::user('finance@grahapondasi.test');
        $director = DemoDataSeeder::user('direktur@grahapondasi.test');
        $pm = DemoDataSeeder::user('pm@grahapondasi.test');

        // --- Inventory ---
        $ton = Unit::firstOrCreate(['company_id' => $companyId, 'code' => 'TON'], ['name' => 'Ton']);
        $sak = Unit::firstOrCreate(['company_id' => $companyId, 'code' => 'SAK'], ['name' => 'Sak']);
        $m3 = Unit::firstOrCreate(['company_id' => $companyId, 'code' => 'M3'], ['name' => 'Meter Kubik']);
        $liter = Unit::firstOrCreate(['company_id' => $companyId, 'code' => 'LTR'], ['name' => 'Liter']);
        $warehouse = Warehouse::firstOrCreate(['company_id' => $companyId, 'code' => 'WH-JKT'], ['name' => 'Gudang Jakarta Timur']);
        $bins = collect([
            'A1' => $warehouse->bins()->firstOrCreate(['code' => 'A1'], ['name' => 'Rak Besi']),
            'B1' => $warehouse->bins()->firstOrCreate(['code' => 'B1'], ['name' => 'Area Bentonite']),
            'C1' => $warehouse->bins()->firstOrCreate(['code' => 'C1'], ['name' => 'Area Sparepart']),
        ]);
        foreach ([['ITM-BESI', 'Besi Tulangan D16', $ton->id, 'A1', '14500000', '50'], ['ITM-BENTONITE', 'Bentonite Drilling Grade', $sak->id, 'B1', '85000', '400'], ['ITM-SPARE', 'Sparepart Rig Bucket Teeth', $liter->id === null ? $sak->id : $sak->id, 'C1', '750000', '20']] as [$sku, $name, $unitId, $binCode, $cost, $qty]) {
            $item = Item::firstOrCreate(['company_id' => $companyId, 'sku' => $sku], ['name' => $name, 'category' => 'Material', 'unit_id' => $unitId]);
            if (! StockBalance::where('company_id', $companyId)->where('item_id', $item->id)->exists()) {
                app(InventoryService::class)->post([
                    'company_id' => $companyId, 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'warehouse_bin_id' => $bins[$binCode]->id,
                ], 'adjustment_in', $qty, 'demo-stock:'.$sku, $warehouseUser, ['type' => 'opening_balance', 'id' => $sku], $cost);
            }
        }

        // --- Procurement: PO → approval → GR → vendor invoice → matching → payment ---
        $vendor = Vendor::where('company_id', $companyId)->where('code', 'VEND-001')->first();
        $item = Item::where('company_id', $companyId)->where('sku', 'ITM-BESI')->first();

        $orderService = app(PurchaseOrderService::class);
        $order = PurchaseOrder::firstOrCreate(['company_id' => $companyId, 'number' => 'PO-DEMO-001'], [
            'vendor_id' => $vendor->id, 'order_date' => now()->subDays(10)->toDateString(), 'currency' => 'IDR',
            'created_by' => $procurement->id, 'status' => 'draft',
        ]);
        if ($order->items()->count() === 0) {
            $order->items()->create(['item_id' => $item->id, 'quantity' => '5', 'unit_price' => '15000000']);
            $orderService->recalculate($order);
        }
        if ($order->status === 'draft') {
            $workflow = ApprovalWorkflow::firstOrCreate(['company_id' => $companyId, 'name' => 'Approval PO Umum', 'document_type' => 'purchase_order']);
            ApprovalStep::firstOrCreate(['approval_workflow_id' => $workflow->id, 'sequence' => 1], ['action' => 'approve', 'mode' => 'any', 'role_id' => Role::where('company_id', $companyId)->where('code', 'director')->firstOrFail()->id, 'sla_hours' => 24]);
            ApprovalRequest::firstOrCreate(['company_id' => $companyId, 'idempotency_key' => 'demo-po-approval'], [
                'approval_workflow_id' => $workflow->id, 'approvable_type' => PurchaseOrder::class, 'approvable_id' => $order->id,
                'submitted_by' => $procurement->id, 'status' => 'approved', 'current_sequence' => 1, 'submitted_at' => now(), 'completed_at' => now(),
            ]);
            $orderService->activateApproved($order, $director);
        }
        if (! GoodsReceipt::where('purchase_order_id', $order->id)->exists() && in_array($order->fresh()->status, ['approved', 'issued', 'partially_received'], true)) {
            $orderService->receive($order->fresh(), $warehouse->id, [[
                'purchase_order_item_id' => $order->items()->first()->id,
                'warehouse_bin_id' => $bins['A1']->id,
                'quantity' => '5',
            ]], 'GR-DEMO-001', 'demo-gr-001', $procurement);
        }
        $invoice = VendorInvoice::where('company_id', $companyId)->where('number', 'INV-BESI-88')->first();
        if (! $invoice) {
            $order->refresh();
            $subtotal = (string) $order->total;
            $ppnIn = TaxRate::firstOrCreate(['company_id' => $companyId, 'code' => 'PPN-MASUKAN'], ['name' => 'PPN Masukan 11%', 'kind' => 'ppn_input', 'rate_percent' => '11']);
            $taxAmount = bcdiv(bcmul($subtotal, (string) $ppnIn->rate_percent, 4), '100', 2);
            $invoice = VendorInvoice::create([
                'company_id' => $companyId, 'vendor_id' => $vendor->id, 'purchase_order_id' => $order->id,
                'number' => 'INV-BESI-88', 'invoice_date' => now()->subDays(6)->toDateString(),
                'subtotal' => $subtotal, 'tax_rate_id' => $ppnIn->id, 'tax_amount' => $taxAmount, 'total' => bcadd($subtotal, $taxAmount, 2),
            ]);
            app(PurchaseOrderService::class)->match($invoice);
        }
        $receipt = GoodsReceipt::where('purchase_order_id', $order->id)->first();
        if ($receipt && ! DB::table('journals')->where('company_id', $companyId)->where('source_type', 'goods_receipt')->where('source_id', (string) $receipt->id)->exists()) {
            app(ProcurementAccountingService::class)->postGoodsReceipt($receipt, $finance);
        }
        if (! DB::table('journals')->where('company_id', $companyId)->where('source_type', 'vendor_invoice')->where('source_id', (string) $invoice->id)->exists()) {
            app(ProcurementAccountingService::class)->postVendorInvoice($invoice->refresh(), $finance);
        }
        if (! $invoice->vendorPayments()->exists()) {
            $bank = BankAccount::firstOrCreate(['company_id' => $companyId, 'code' => 'BCA-OPS'], [
                'account_id' => Account::where('company_id', $companyId)->where('code', '1-1000')->value('id'),
                'bank_name' => 'BCA', 'account_name' => 'PT Graha Pondasi Operasional', 'account_number' => '5410123456', 'currency' => 'IDR',
            ]);
            $pph23 = TaxRate::firstOrCreate(['company_id' => $companyId, 'code' => 'PPH23'], ['name' => 'PPh Pasal 23 2%', 'kind' => 'withholding', 'rate_percent' => '2']);
            app(CashBankService::class)->payVendor($invoice->refresh(), $bank, '41625000', now()->subDays(3)->toDateString(), 'PAY-DEMO-001', 'TRF-99182X', 'demo-pay-001', $finance, [
                'tax_rate_id' => (string) $pph23->id, 'bukti_potong_number' => 'BP-23/'.now()->format('Y').'/001', 'bukti_potong_date' => now()->subDays(3)->toDateString(),
            ]);
            app(CashBankService::class)->payVendor($invoice->refresh(), $bank, '40792500', now()->subDays(2)->toDateString(), 'PAY-DEMO-002', 'TRF-99183X', 'demo-pay-002', $finance);
        }

        // --- Equipment: rig ×2 + crane + excavator + generator + fuel tank ---
        $equipmentSpecs = [
            ['EQ-RIG-01', 'Bored Pile Rig SOILMEC R-516', 'rig', 'operational', '12480'],
            ['EQ-RIG-02', 'Bored Pile Rig BAUER BG-22', 'rig', 'breakdown', '9875'],
            ['EQ-CRN-01', 'Crawler Crane Hitachi KH180', 'crane', 'operational', '15230'],
            ['EQ-EXC-01', 'Excavator CAT 320', 'excavator', 'maintenance', '8320'],
            ['EQ-GEN-01', 'Genset Silent 100 kVA', 'generator', 'operational', '4210'],
        ];
        foreach ($equipmentSpecs as [$code, $name, $category, $status, $hm]) {
            Equipment::firstOrCreate(['company_id' => $companyId, 'code' => $code], [
                'name' => $name, 'ownership' => $category === 'crane' ? 'rented' : 'owned',
                'category' => $category, 'current_hour_meter' => $hm,
                'fuel_target_lph' => $category === 'rig' ? '18.5' : '12', 'status' => $status,
            ]);
        }
        $rig = Equipment::where('company_id', $companyId)->where('code', 'EQ-RIG-01')->firstOrFail();
        if (! EquipmentMeterLog::where('equipment_id', $rig->id)->exists()) {
            app(EquipmentService::class)->recordMeter($rig, '12480', $pm);
            FuelUsage::create([
                'company_id' => $companyId, 'equipment_id' => $rig->id,
                'liters' => '142.5000', 'start_meter' => '12400.00', 'end_meter' => '12480.00',
                'liters_per_hour' => '17.8125', 'reference' => 'demo-fuel-'.now()->format('Ymd'),
                'recorded_by' => $warehouseUser->id, 'used_at' => now()->subDay(),
            ]);
        }
        $rigBroken = Equipment::where('company_id', $companyId)->where('code', 'EQ-RIG-02')->first();
        if ($rigBroken && ! EquipmentDowntimeLog::where('equipment_id', $rigBroken->id)->exists()) {
            EquipmentDowntimeLog::create([
                'company_id' => $companyId, 'equipment_id' => $rigBroken->id,
                'started_at' => now()->subDays(3), 'reason' => 'breakdown', 'delay_reason' => 'rig_breakdown',
                'notes' => 'Demo seed: kerusakan hidraulik main pump — tunggu sparepart.', 'recorded_by' => $pm->id,
            ]);
        }
    }
}
