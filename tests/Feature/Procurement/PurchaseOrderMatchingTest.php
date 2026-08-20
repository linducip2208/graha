<?php

namespace Tests\Feature\Procurement;

use App\Models\Company;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorInvoice;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseOrderMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_revision_receipt_idempotency_and_quantity_cap(): void
    {
        [$order, $item, $warehouse, $bin, $user] = $this->fixture();
        $service = app(PurchaseOrderService::class);
        $revised = $service->revise($order, [], 'Harga berubah', $user);
        $this->assertSame(2, $revised->version);
        $this->assertDatabaseHas('purchase_order_revisions', ['purchase_order_id' => $order->id, 'version' => 1]);
        $revised->update(['status' => 'approved']);
        $line = ['purchase_order_item_id' => $item->id, 'warehouse_bin_id' => $bin->id, 'quantity' => '5'];
        $receipt = $service->receive($revised, $warehouse->id, [$line], 'GR-1', 'gr-key', $user);
        $same = $service->receive($revised, $warehouse->id, [$line], 'GR-1', 'gr-key', $user);
        $this->assertSame($receipt->id, $same->id);
        $this->assertDatabaseHas('stock_balances', ['item_id' => $item->item_id, 'quantity' => 5]);
        $this->expectException(ValidationException::class);
        $service->receive($revised->refresh(), $warehouse->id, [['purchase_order_item_id' => $item->id, 'warehouse_bin_id' => $bin->id, 'quantity' => '6']], 'GR-2', 'gr-key-2', $user);
    }

    public function test_three_way_match_requires_received_quantity_and_equal_amount(): void
    {
        [$order, $item, $warehouse, $bin, $user, $vendor] = $this->fixture();
        $service = app(PurchaseOrderService::class);
        $order->update(['status' => 'approved']);
        $service->receive($order, $warehouse->id, [['purchase_order_item_id' => $item->id, 'warehouse_bin_id' => $bin->id, 'quantity' => '10']], 'GR-1', 'match-gr', $user);
        $invoice = VendorInvoice::create(['company_id' => $order->company_id, 'vendor_id' => $vendor->id, 'purchase_order_id' => $order->id, 'number' => 'INV-1', 'invoice_date' => today(), 'total' => '1000']);
        $this->assertSame('matched', $service->match($invoice)->match_status);
        $invoice2 = VendorInvoice::create(['company_id' => $order->company_id, 'vendor_id' => $vendor->id, 'purchase_order_id' => $order->id, 'number' => 'INV-2', 'invoice_date' => today(), 'total' => '999']);
        $this->assertSame('exception', $service->match($invoice2)->match_status);
    }

    public function test_purchase_order_cannot_activate_without_completed_approval(): void
    {
        [$order, , , , $user] = $this->fixture();
        $this->expectException(ValidationException::class);
        app(PurchaseOrderService::class)->activateApproved($order, $user);
    }

    private function fixture(): array
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $user = User::factory()->create();
        $unit = Unit::create(['company_id' => $company->id, 'code' => 'EA', 'name' => 'Each']);
        $stockItem = Item::create(['company_id' => $company->id, 'unit_id' => $unit->id, 'sku' => 'BAR', 'name' => 'Besi', 'category' => 'material']);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'code' => 'W', 'name' => 'Gudang']);
        $bin = WarehouseBin::create(['warehouse_id' => $warehouse->id, 'code' => 'A', 'name' => 'A']);
        $vendor = Vendor::create(['company_id' => $company->id, 'code' => 'V1', 'name' => 'Vendor']);
        $order = PurchaseOrder::create(['company_id' => $company->id, 'vendor_id' => $vendor->id, 'number' => 'PO-1', 'order_date' => today(), 'created_by' => $user->id]);
        $item = $order->items()->create(['item_id' => $stockItem->id, 'quantity' => '10', 'unit_price' => '100']);
        $order = app(PurchaseOrderService::class)->recalculate($order);

        return [$order, $item, $warehouse, $bin, $user, $vendor];
    }
}
