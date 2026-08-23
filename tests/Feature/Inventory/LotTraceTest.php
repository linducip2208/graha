<?php

namespace Tests\Feature\Inventory;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LotTraceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lot_trace_shows_receipt_and_issue_chain(): void
    {
        [$company, $user, $bin, $item] = $this->fixture();
        app(InventoryService::class)->post(
            ['company_id' => $company->id, 'item_id' => $item->id, 'warehouse_id' => $bin->warehouse_id, 'warehouse_bin_id' => $bin->id, 'lot_number' => 'HEAT-2026-01'],
            'receipt', '1000', 'lot-in-1', $user, ['type' => 'goods_receipt', 'id' => 55], '15000'
        );
        app(InventoryService::class)->post(
            ['company_id' => $company->id, 'item_id' => $item->id, 'warehouse_id' => $bin->warehouse_id, 'warehouse_bin_id' => $bin->id, 'lot_number' => 'HEAT-2026-01', 'project_id' => null],
            'issue', '250', 'lot-out-1', $user, ['type' => 'reinforcement_cage', 'id' => 9]
        );

        $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => 'inv-view'], ['name' => 'Inv Viewer']);
        $permission = Permission::firstOrCreate(['code' => 'inventory.view'], ['name' => 'inventory.view', 'module' => 'inventory']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $membership = DB::table('company_user')->where('company_id', $company->id)->where('user_id', $user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);

        $page = $this->actingAs($user)->withSession(['company_id' => $company->id])
            ->get('/admin/inventory/lots?lot=HEAT-2026-01');
        $page->assertOk()
            ->assertSee('HEAT-2026-01')
            ->assertSee('GR #55')
            ->assertSee('Cage #9')
            ->assertSee('RECEIPT')
            ->assertSee('ISSUE');

        // Lot tak dikenal → empty state, bukan error.
        $this->get('/admin/inventory/lots?lot=TIDAK-ADA')->assertOk()->assertSee('Tidak ada pergerakan');
    }

    private function fixture(string $code = 'GP'): array
    {
        $company = Company::create(['code' => $code, 'name' => $code]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C-LT', 'name' => 'Klien']);
        Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P-LT', 'name' => 'Proyek Lot', 'status' => 'in_progress']);
        $unit = Unit::create(['company_id' => $company->id, 'code' => 'KG', 'name' => 'Kilogram']);
        $item = Item::create(['company_id' => $company->id, 'unit_id' => $unit->id, 'sku' => 'BAJA-LT', 'name' => 'Baja D16 Lot', 'category' => 'steel']);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'code' => 'WH-LT', 'name' => 'Gudang Lot']);
        $bin = WarehouseBin::create(['warehouse_id' => $warehouse->id, 'code' => 'B1', 'name' => 'Bin 1']);

        return [$company, $user, $bin, $item];
    }
}
