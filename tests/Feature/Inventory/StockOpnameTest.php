<?php

namespace Tests\Feature\Inventory;

use App\Models\Company;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\StockOpnameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockOpnameTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_posts_adjustment_once_and_skips_zero_variance(): void
    {
        [$company, $counter, $approver, $context] = $this->fixture();
        [$itemA, $itemB] = $this->items($company, $context);
        $service = app(StockOpnameService::class);

        $count = StockCount::create(['company_id' => $company->id, 'warehouse_id' => $context['warehouse']->id, 'number' => 'OPN-1', 'status' => 'draft', 'counted_by' => $counter->id]);
        $count->lines()->createMany([
            ['item_id' => $itemA->id, 'warehouse_bin_id' => $context['binA']->id, 'lot_number' => '', 'system_quantity' => '50', 'counted_quantity' => '47.5'],
            ['item_id' => $itemB->id, 'warehouse_bin_id' => $context['binB']->id, 'lot_number' => '', 'system_quantity' => '80', 'counted_quantity' => '80'],
        ]);

        $approved = $service->approve($count->refresh(), $approver);
        $again = $service->approve($approved, $approver);

        $this->assertSame('approved', $again->status);
        $balanceA = StockBalance::where('item_id', $itemA->id)->where('warehouse_id', $context['warehouse']->id)->first();
        $this->assertSame('47.5000', (string) $balanceA->quantity);
        $balanceB = StockBalance::where('item_id', $itemB->id)->first();
        $this->assertSame('80.0000', (string) $balanceB->quantity);
        $this->assertSame(1, StockMovement::whereIn('movement_type', ['adjustment_in', 'adjustment_out'])->count());
    }

    public function test_counter_cannot_approve_own_count(): void
    {
        [$company, $counter, , $context] = $this->fixture();
        [$itemA] = $this->items($company, $context);
        $count = StockCount::create(['company_id' => $company->id, 'warehouse_id' => $context['warehouse']->id, 'number' => 'OPN-2', 'status' => 'draft', 'counted_by' => $counter->id]);
        $count->lines()->create(['item_id' => $itemA->id, 'warehouse_bin_id' => $context['binA']->id, 'lot_number' => '', 'system_quantity' => '10', 'counted_quantity' => '9']);

        $this->expectException(ValidationException::class);
        app(StockOpnameService::class)->approve($count, $counter);
    }

    public function test_negative_counted_quantity_rejected_at_creation(): void
    {
        [$company, $counter, , $context] = $this->fixture();
        [$itemA] = $this->items($company, $context);
        $service = app(StockOpnameService::class);

        $this->expectException(ValidationException::class);
        $service->create($company->id, [
            'number' => 'OPN-3', 'warehouse_id' => $context['warehouse']->id,
        ], [
            ['item_id' => $itemA->id, 'counted_quantity' => '-1'],
        ], $counter);
    }

    private function fixture(): array
    {
        $company = Company::create(['code' => 'OPN', 'name' => 'OPN']);
        $counter = User::factory()->create();
        $approver = User::factory()->create();
        foreach ([$counter, $approver] as $u) {
            $u->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        }
        $warehouse = Warehouse::create(['company_id' => $company->id, 'code' => 'WH', 'name' => 'Gudang']);
        $binA = $warehouse->bins()->create(['code' => 'A1', 'name' => 'Bin A']);
        $binB = $warehouse->bins()->create(['code' => 'B1', 'name' => 'Bin B']);

        return [$company, $counter, $approver, ['warehouse' => $warehouse, 'binA' => $binA, 'binB' => $binB]];
    }

    private function items(Company $company, array $context, bool $allowNegative = true): array
    {
        $unit = Unit::create(['company_id' => $company->id, 'code' => 'PCS', 'name' => 'Piece']);
        $inventory = app(InventoryService::class);
        $actor = User::first();
        $itemA = Item::create(['company_id' => $company->id, 'sku' => 'SKU-A', 'name' => 'A', 'category' => 'M', 'unit_id' => $unit->id]);
        $itemB = Item::create(['company_id' => $company->id, 'sku' => 'SKU-B', 'name' => 'B', 'category' => 'M', 'unit_id' => $unit->id]);
        foreach ([[$itemA, 50, $context['binA']], [$itemB, 80, $context['binB']]] as [$item, $qty, $bin]) {
            $inventory->post(['company_id' => $company->id, 'item_id' => $item->id, 'warehouse_id' => $context['warehouse']->id, 'warehouse_bin_id' => $bin->id], 'receipt', (string) $qty, 'seed:'.$item->sku, $actor, ['type' => 'opening', 'id' => $item->sku], '1000');
        }

        return [$itemA, $itemB];
    }
}
