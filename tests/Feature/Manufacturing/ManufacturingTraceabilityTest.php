<?php

namespace Tests\Feature\Manufacturing;

use App\Models\BillOfMaterial;
use App\Models\Company;
use App\Models\Item;
use App\Models\ProductionOrder;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Services\InventoryService;
use App\Services\ManufacturingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManufacturingTraceabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_issue_and_completion_are_linked_to_stock_ledger(): void
    {
        $c = Company::create(['code' => 'GP', 'name' => 'GP']);
        $u = User::factory()->create();
        $unit = Unit::create(['company_id' => $c->id, 'code' => 'EA', 'name' => 'Each']);
        $raw = Item::create(['company_id' => $c->id, 'unit_id' => $unit->id, 'sku' => 'RAW', 'name' => 'Raw', 'category' => 'material']);
        $output = Item::create(['company_id' => $c->id, 'unit_id' => $unit->id, 'sku' => 'CAGE', 'name' => 'Cage', 'category' => 'finished_good']);
        $w = Warehouse::create(['company_id' => $c->id, 'code' => 'W', 'name' => 'W']);
        $bin = WarehouseBin::create(['warehouse_id' => $w->id, 'code' => 'A', 'name' => 'A']);
        $bom = BillOfMaterial::create(['company_id' => $c->id, 'output_item_id' => $output->id, 'code' => 'BOM-CAGE', 'version' => 1, 'status' => 'active']);
        $bom->items()->create(['item_id' => $raw->id, 'quantity' => '2']);
        $order = ProductionOrder::create(['company_id' => $c->id, 'bill_of_material_id' => $bom->id, 'warehouse_id' => $w->id, 'output_bin_id' => $bin->id, 'number' => 'MO-1', 'planned_quantity' => '1', 'created_by' => $u->id]);
        $dimension = ['warehouse_id' => $w->id, 'warehouse_bin_id' => $bin->id];
        app(InventoryService::class)->post([...$dimension, 'company_id' => $c->id, 'item_id' => $raw->id], 'receipt', '2', 'raw-receipt', $u, ['type' => 'gr', 'id' => '1']);
        app(ManufacturingService::class)->issueMaterial($order, $raw, $dimension, '2', $u, 'issue');
        $done = app(ManufacturingService::class)->complete($order, '1', $u, 'done');
        $this->assertSame('completed', $done->status);
        $this->assertDatabaseHas('production_material_issues', ['production_order_id' => $order->id, 'item_id' => $raw->id]);
        $this->assertDatabaseHas('stock_balances', ['item_id' => $raw->id, 'quantity' => 0]);
        $this->assertDatabaseHas('stock_balances', ['item_id' => $output->id, 'quantity' => 1]);
    }
}
