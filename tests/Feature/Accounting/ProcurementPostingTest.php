<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Item;
use App\Models\NumberSequence;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorInvoice;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Services\ProcurementAccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProcurementPostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_goods_receipt_and_matched_invoice_post_balanced_configured_journals(): void
    {
        [$company, $user, $receipt, $invoice] = $this->fixture();
        $inventory = Account::create(['company_id' => $company->id, 'code' => 'INV', 'name' => 'Inventory', 'type' => 'asset', 'normal_balance' => 'debit']);
        $grni = Account::create(['company_id' => $company->id, 'code' => 'GRNI', 'name' => 'GRNI', 'type' => 'liability', 'normal_balance' => 'credit']);
        $ap = Account::create(['company_id' => $company->id, 'code' => 'AP', 'name' => 'AP', 'type' => 'liability', 'normal_balance' => 'credit']);
        foreach ([['goods_receipt', 'debit', $inventory], ['goods_receipt', 'credit', $grni], ['vendor_invoice', 'debit', $grni], ['vendor_invoice', 'credit', $ap]] as [$event, $side, $account]) {
            AccountingMapping::create(['company_id' => $company->id, 'event_type' => $event, 'entry_side' => $side, 'account_id' => $account->id]);
        }
        $service = app(ProcurementAccountingService::class);
        $grJournal = $service->postGoodsReceipt($receipt, $user);
        $invoiceJournal = $service->postVendorInvoice($invoice, $user);
        $this->assertSame('1000.00', $grJournal->entries->reduce(fn ($sum, $entry) => bcadd($sum, $entry->debit, 2), '0'));
        $this->assertSame('1000.00', $invoiceJournal->entries->reduce(fn ($sum, $entry) => bcadd($sum, $entry->credit, 2), '0'));
        $this->assertSame($grJournal->id, $service->postGoodsReceipt($receipt, $user)->id);
    }

    public function test_unmatched_invoice_cannot_post(): void
    {
        [, $user, , $invoice] = $this->fixture();
        $invoice->update(['match_status' => 'exception']);
        $this->expectException(ValidationException::class);
        app(ProcurementAccountingService::class)->postVendorInvoice($invoice->refresh(), $user);
    }

    private function fixture(): array
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $user = User::factory()->create();
        NumberSequence::create(['company_id' => $company->id, 'document_type' => 'journal', 'prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);
        FiscalPeriod::create(['company_id' => $company->id, 'name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        $unit = Unit::create(['company_id' => $company->id, 'code' => 'EA', 'name' => 'Each']);
        $item = Item::create(['company_id' => $company->id, 'unit_id' => $unit->id, 'sku' => 'BAR', 'name' => 'Besi', 'category' => 'material']);
        $vendor = Vendor::create(['company_id' => $company->id, 'code' => 'V', 'name' => 'Vendor']);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'code' => 'W', 'name' => 'W']);
        $bin = WarehouseBin::create(['warehouse_id' => $warehouse->id, 'code' => 'A', 'name' => 'A']);
        $order = PurchaseOrder::create(['company_id' => $company->id, 'vendor_id' => $vendor->id, 'number' => 'PO', 'order_date' => today(), 'total' => '1000', 'status' => 'received', 'created_by' => $user->id]);
        $poItem = $order->items()->create(['item_id' => $item->id, 'quantity' => '10', 'received_quantity' => '10', 'unit_price' => '100']);
        $movement = StockMovement::create(['transaction_id' => fake()->uuid(), 'company_id' => $company->id, 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'warehouse_bin_id' => $bin->id, 'lot_number' => '', 'movement_type' => 'receipt', 'quantity' => '10', 'balance_after' => '10', 'unit_cost' => '100', 'reference_type' => 'goods_receipt', 'reference_id' => '1', 'idempotency_key' => 'gr', 'posted_by' => $user->id, 'posted_at' => now()]);
        $receipt = GoodsReceipt::create(['company_id' => $company->id, 'purchase_order_id' => $order->id, 'warehouse_id' => $warehouse->id, 'number' => 'GR', 'received_at' => now(), 'received_by' => $user->id, 'idempotency_key' => 'gr']);
        GoodsReceiptItem::create(['goods_receipt_id' => $receipt->id, 'purchase_order_item_id' => $poItem->id, 'warehouse_bin_id' => $bin->id, 'quantity' => '10', 'stock_movement_id' => $movement->id]);
        $invoice = VendorInvoice::create(['company_id' => $company->id, 'vendor_id' => $vendor->id, 'purchase_order_id' => $order->id, 'number' => 'INV', 'invoice_date' => today(), 'total' => '1000', 'match_status' => 'matched']);

        return [$company, $user, $receipt, $invoice];
    }
}
