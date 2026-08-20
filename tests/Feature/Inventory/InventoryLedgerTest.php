<?php

namespace Tests\Feature\Inventory;

use App\Models\Company;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function base(): array
    {
        $c = Company::create(['code' => 'GP', 'name' => 'GP']);
        $uom = Unit::create(['company_id' => $c->id, 'code' => 'KG', 'name' => 'Kilogram']);
        $item = Item::create(['company_id' => $c->id, 'unit_id' => $uom->id, 'sku' => 'BESI', 'name' => 'Besi', 'category' => 'material']);
        $w1 = Warehouse::create(['company_id' => $c->id, 'code' => 'W1', 'name' => 'Utama']);
        $w2 = Warehouse::create(['company_id' => $c->id, 'code' => 'W2', 'name' => 'Proyek']);
        $b1 = WarehouseBin::create(['warehouse_id' => $w1->id, 'code' => 'A', 'name' => 'A']);
        $b2 = WarehouseBin::create(['warehouse_id' => $w2->id, 'code' => 'B', 'name' => 'B']);

        return [$c, $item, $w1, $w2, $b1, $b2, User::factory()->create()];
    }

    public function test_receipt_is_idempotent_and_issue_prevents_negative_stock(): void
    {
        [$c,$item,$w1,,$b1,,$user] = $this->base();
        $d = ['company_id' => $c->id, 'item_id' => $item->id, 'warehouse_id' => $w1->id, 'warehouse_bin_id' => $b1->id];
        $service = app(InventoryService::class);
        $first = $service->post($d, 'receipt', '10.0000', 'receipt-1', $user, ['type' => 'gr', 'id' => '1'], '5000');
        $second = $service->post($d, 'receipt', '10.0000', 'receipt-1', $user, ['type' => 'gr', 'id' => '1'], '5000');
        $this->assertSame($first->id, $second->id);
        $service->post($d, 'issue', '7.5000', 'issue-1', $user, ['type' => 'material_issue', 'id' => '1']);
        $this->assertDatabaseHas('stock_balances', ['item_id' => $item->id, 'quantity' => 2.5]);
        $this->expectException(ValidationException::class);
        $service->post($d, 'issue', '3.0000', 'issue-2', $user, ['type' => 'material_issue', 'id' => '2']);
    }

    public function test_transfer_is_balanced_and_ledger_immutable(): void
    {
        [$c,$item,$w1,$w2,$b1,$b2,$user] = $this->base();
        $service = app(InventoryService::class);
        $from = ['company_id' => $c->id, 'item_id' => $item->id, 'warehouse_id' => $w1->id, 'warehouse_bin_id' => $b1->id];
        $to = ['company_id' => $c->id, 'item_id' => $item->id, 'warehouse_id' => $w2->id, 'warehouse_bin_id' => $b2->id];
        $service->post($from, 'receipt', '5', 'r', $user, ['type' => 'gr', 'id' => '1']);
        [$out,$in] = $service->transfer($from, $to, '2', 't', $user);
        $this->assertSame('-2.0000', $out->quantity);
        $this->assertSame('2.0000', $in->quantity);
        $this->expectException(\LogicException::class);
        StockMovement::first()->update(['quantity' => '999']);
    }
}
