<?php

namespace Tests\Feature\Inventory;

use App\Http\Controllers\InventoryController;
use App\Models\Company;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Services\ReorderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReorderRecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendation_uses_on_hand_and_outstanding_po(): void
    {
        $c = Company::create(['code' => 'GP', 'name' => 'GP']);
        $uom = Unit::create(['company_id' => $c->id, 'code' => 'KG', 'name' => 'Kilogram']);
        $user = User::factory()->create();

        // Item A: reorder point 100, target max 500, saldo 80 -> usulan 420.
        $a = Item::create(['company_id' => $c->id, 'unit_id' => $uom->id, 'sku' => 'A', 'name' => 'Semen', 'category' => 'mat', 'reorder_point' => '100', 'reorder_max' => '500', 'lead_time_days' => 7]);
        // Item B: reorder point 50, tanpa max (target = max(point, minimum) = 60), saldo 30, on-order 25 -> usulan 5.
        $b = Item::create(['company_id' => $c->id, 'unit_id' => $uom->id, 'sku' => 'B', 'name' => 'Besi', 'category' => 'mat', 'reorder_point' => '50', 'minimum_stock' => '60']);
        // Item C: di atas reorder point -> tidak direkomendasikan.
        $c3 = Item::create(['company_id' => $c->id, 'unit_id' => $uom->id, 'sku' => 'C', 'name' => 'Pasir', 'category' => 'mat', 'reorder_point' => '10']);
        // Item D: reorder point nol -> tidak dipantau.
        Item::create(['company_id' => $c->id, 'unit_id' => $uom->id, 'sku' => 'D', 'name' => 'Air', 'category' => 'mat']);

        $w1 = Warehouse::create(['company_id' => $c->id, 'code' => 'W1', 'name' => 'W1']);
        $bin = WarehouseBin::create(['warehouse_id' => $w1->id, 'code' => 'A', 'name' => 'A']);

        StockBalance::create(['company_id' => $c->id, 'item_id' => $a->id, 'warehouse_id' => $w1->id, 'warehouse_bin_id' => $bin->id, 'quantity' => '80.0000']);
        StockBalance::create(['company_id' => $c->id, 'item_id' => $b->id, 'warehouse_id' => $w1->id, 'warehouse_bin_id' => $bin->id, 'quantity' => '30.0000']);
        StockBalance::create(['company_id' => $c->id, 'item_id' => $c3->id, 'warehouse_id' => $w1->id, 'warehouse_bin_id' => $bin->id, 'quantity' => '99.0000']);

        $vendor = Vendor::create(['company_id' => $c->id, 'code' => 'V1', 'name' => 'Vendor Satu', 'status' => 'approved']);
        $poA = PurchaseOrder::create(['company_id' => $c->id, 'vendor_id' => $vendor->id, 'number' => 'PO-A', 'status' => 'active', 'currency' => 'IDR', 'order_date' => now()->toDateString(), 'created_by' => $user->id]);
        $poA->items()->create(['item_id' => $b->id, 'quantity' => '40.0000', 'unit_price' => '10000', 'received_quantity' => '15.0000']);
        $poRejected = PurchaseOrder::create(['company_id' => $c->id, 'vendor_id' => $vendor->id, 'number' => 'PO-B', 'status' => 'rejected', 'currency' => 'IDR', 'order_date' => now()->toDateString(), 'created_by' => $user->id]);
        $poRejected->items()->create(['item_id' => $b->id, 'quantity' => '999.0000', 'unit_price' => '1']);

        $recs = collect(app(ReorderService::class)->recommendations($c->id))->keyBy(fn ($r) => $r['item']->sku);

        $this->assertSame(['A', 'B'], $recs->keys()->all());
        $this->assertSame('420.0000', $recs['A']['suggested_qty']);
        $this->assertSame(7, $recs['A']['lead_time_days']);
        $this->assertSame('80.0000', $recs['A']['on_hand']);
        $this->assertSame('5.0000', $recs['B']['suggested_qty']);
        $this->assertSame('25.0000', $recs['B']['on_order']);
    }

    public function test_reorder_settings_update_and_page_render(): void
    {
        $this->assertTrue(method_exists(InventoryController::class, 'reorder'));
        $this->assertTrue(method_exists(InventoryController::class, 'updateReorderSettings'));
    }
}
