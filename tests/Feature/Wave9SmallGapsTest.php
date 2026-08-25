<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ContractChange;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorInvoice;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Services\InventoryService;
use App\Services\PurchaseOrderService;
use App\Services\RfqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Wave9SmallGapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_v1_contracts_endpoint_scoped_and_permitted(): void
    {
        [$companyA, $user] = $this->apiFixture('W9A');
        [$companyB] = $this->apiFixture('W9B');
        Permission::firstOrCreate(['code' => 'contract.view'], ['name' => 'Contract View', 'module' => 'contract']);
        $role = Role::create(['company_id' => $companyA->id, 'code' => 'con-api', 'name' => 'Con API']);
        $role->permissions()->attach(Permission::where('code', 'contract.view')->first()->id);
        $pivot = (int) DB::table('company_user')->where(['company_id' => $companyA->id, 'user_id' => $user->id])->value('id');
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $pivot, 'role_id' => $role->id]);
        ContractChange::create(['company_id' => $companyA->id, 'number' => 'VO-1', 'type' => 'variation_order', 'title' => 'Tambah titik pile', 'amount' => '150000000', 'status' => 'draft', 'currency' => 'IDR', 'idempotency_key' => 'w9-vo-1']);
        $token = $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'password', 'device' => 'test'])->json('token');

        $this->getJson('/api/v1/contracts', ['Authorization' => "Bearer {$token}", 'X-Company-Id' => (string) $companyA->id])
            ->assertOk()->assertJsonPath('data.data.0.number', 'VO-1');
        $this->getJson('/api/v1/contracts', ['Authorization' => "Bearer {$token}", 'X-Company-Id' => (string) $companyB->id])->assertStatus(403);
    }

    public function test_quotation_warranty_column_in_comparison(): void
    {
        [$rfq, $user, $vendorA, $vendorB, $suffix] = $this->rfqFixture();
        $service = app(RfqService::class);
        $service->invite($rfq, [$vendorA->id, $vendorB->id], $user);
        $service->submitQuotation($rfq, $vendorA->id, ['number' => 'Q-A', 'delivery_lead_days' => 7, 'warranty_months' => 12], [
            ['item_id' => $this->itemId($rfq, 'ITM-A'.$suffix), 'quantity' => '10', 'unit_price' => '100'],
            ['item_id' => $this->itemId($rfq, 'ITM-B'.$suffix), 'quantity' => '5', 'unit_price' => '200'],
        ], $user);
        $service->submitQuotation($rfq, $vendorB->id, ['number' => 'Q-B'], [
            ['item_id' => $this->itemId($rfq, 'ITM-A'.$suffix), 'quantity' => '10', 'unit_price' => '90'],
            ['item_id' => $this->itemId($rfq, 'ITM-B'.$suffix), 'quantity' => '5', 'unit_price' => '200'],
        ], $user);

        $comparison = $service->compare($rfq->refresh());
        $this->assertSame(12, $comparison->firstWhere('vendor', 'A')['warranty']);
        $this->assertNull($comparison->firstWhere('vendor', 'B')['warranty']);
    }

    public function test_three_way_match_reports_granular_flags(): void
    {
        [$order, $item, $warehouse, $bin, $user, $vendor] = $this->poFixture();
        $service = app(PurchaseOrderService::class);
        $order->update(['status' => 'approved']);

        // Terima sebagian (6 dari 10): quantity_flag short + rincian item.
        $service->receive($order->refresh(), $warehouse->id, [['purchase_order_item_id' => $item->id, 'warehouse_bin_id' => $bin->id, 'quantity' => '6']], 'GR-PART', 'w9-gr', $user);
        $invoiceShort = VendorInvoice::create(['company_id' => $order->company_id, 'vendor_id' => $vendor->id, 'purchase_order_id' => $order->id, 'number' => 'INV-S', 'invoice_date' => today(), 'total' => '600']);
        $details = $service->match($invoiceShort)->match_details;
        $this->assertSame('short', $details['quantity_flag']);
        $this->assertSame([['purchase_order_item_id' => $item->id, 'ordered' => '10.0000', 'received' => '6.0000']], $details['short_items']);
        $this->assertSame('400.00', $details['amount_difference']);

        // Terima sisa: full, selisih nol, tanpa short item.
        $service->receive($order->refresh(), $warehouse->id, [['purchase_order_item_id' => $item->id, 'warehouse_bin_id' => $bin->id, 'quantity' => '4']], 'GR-REST', 'w9-gr2', $user);
        $invoiceFull = VendorInvoice::create(['company_id' => $order->company_id, 'vendor_id' => $vendor->id, 'purchase_order_id' => $order->id, 'number' => 'INV-F', 'invoice_date' => today(), 'total' => '1000']);
        $full = $service->match($invoiceFull)->match_details;
        $this->assertSame('full', $full['quantity_flag']);
        $this->assertSame('0.00', $full['amount_difference']);
        $this->assertSame([], $full['short_items']);
    }

    public function test_stock_condition_buckets_and_in_transit(): void
    {
        [$balance, $user] = $this->stockFixture();
        $service = app(InventoryService::class);

        try {
            $service->flagCondition($balance, 'damaged', '15', $user);
            $this->fail('Flag melebihi stok available harus ditolak.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $flagged = $service->flagCondition($balance, 'damaged', '2', $user);
        $this->assertSame('2.0000', $flagged->damaged_quantity);
        $this->assertSame('10.0000', $flagged->quantity, 'Total fisik tidak berubah.');
        $restored = $service->restoreCondition($flagged, 'damaged', '1', $user);
        $this->assertSame('1.0000', $restored->damaged_quantity);

        $transit = $service->adjustInTransit($balance, '+5', $user);
        $transit = $service->adjustInTransit($transit, '-2', $user);
        $this->assertSame('3.0000', $transit->in_transit_quantity);

        $this->expectException(ValidationException::class);
        $service->adjustInTransit($transit, '-10', $user);
    }

    public function test_condition_http_endpoints_scoped(): void
    {
        [$balance, $user, $company] = $this->stockFixture(withSession: true);
        $this->post('/admin/inventory/balances/'.$balance->id.'/condition', ['action' => 'flag', 'bucket' => 'obsolete', 'quantity' => '1'])->assertRedirect();
        $this->assertDatabaseHas('stock_balances', ['id' => $balance->id, 'obsolete_quantity' => 1]);
        $this->post('/admin/inventory/balances-in-transit', ['balance_id' => $balance->id, 'delta' => '-1'])->assertSessionHasErrors();
        $this->post('/admin/inventory/balances-in-transit', ['balance_id' => $balance->id, 'delta' => '7'])->assertRedirect();
        $this->assertDatabaseHas('stock_balances', ['id' => $balance->id, 'in_transit_quantity' => 7]);
    }

    private function itemId(Rfq $rfq, string $sku): int
    {
        return $rfq->items()->whereHas('item', fn ($q) => $q->where('sku', $sku))->value('item_id');
    }

    private function apiFixture(string $code): array
    {
        $company = Company::create(['code' => $code, 'name' => "GP {$code}"]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C-'.$code, 'name' => 'Client']);
        Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P-'.$code, 'name' => 'Proyek '.$code, 'status' => 'in_progress']);

        return [$company, $user];
    }

    private function rfqFixture(): array
    {
        $suffix = uniqid()[0];
        $company = Company::create(['code' => 'W9R'.$suffix, 'name' => 'GP RFQ W9']);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $unit = Unit::create(['company_id' => $company->id, 'code' => 'PCS', 'name' => 'Piece']);
        foreach ([['ITM-A', 100], ['ITM-B', 200]] as [$sku, $price]) {
            Item::create(['company_id' => $company->id, 'sku' => $sku.$suffix, 'name' => $sku, 'category' => 'Material', 'unit_id' => $unit->id]);
        }
        $rfq = app(RfqService::class)->create($company->id, ['number' => 'RFQ-W9'.$suffix, 'title' => 'Pengadaan uji'], [
            ['sku' => 'ITM-A'.$suffix, 'quantity' => '10'], ['sku' => 'ITM-B'.$suffix, 'quantity' => '5'],
        ], $user);
        $vendorA = Vendor::create(['company_id' => $company->id, 'code' => 'VA9', 'name' => 'A']);
        $vendorB = Vendor::create(['company_id' => $company->id, 'code' => 'VB9', 'name' => 'B']);

        return [$rfq, $user, $vendorA, $vendorB, $suffix];
    }

    private function poFixture(): array
    {
        $company = Company::create(['code' => 'W9P'.uniqid()[0], 'name' => 'GP PO W9']);
        $user = User::factory()->create();
        $unit = Unit::create(['company_id' => $company->id, 'code' => 'EA', 'name' => 'Each']);
        $stockItem = Item::create(['company_id' => $company->id, 'unit_id' => $unit->id, 'sku' => 'BAR9', 'name' => 'Besi', 'category' => 'material']);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'code' => 'W9', 'name' => 'Gudang']);
        $bin = WarehouseBin::create(['warehouse_id' => $warehouse->id, 'code' => 'A', 'name' => 'A']);
        $vendor = Vendor::create(['company_id' => $company->id, 'code' => 'VW9', 'name' => 'Vendor']);
        $order = PurchaseOrder::create(['company_id' => $company->id, 'vendor_id' => $vendor->id, 'number' => 'PO-W9-'.uniqid()[0], 'order_date' => today(), 'created_by' => $user->id]);
        $item = $order->items()->create(['item_id' => $stockItem->id, 'quantity' => '10', 'unit_price' => '100']);
        $order = app(PurchaseOrderService::class)->recalculate($order);

        return [$order, $item, $warehouse, $bin, $user, $vendor];
    }

    private function stockFixture(bool $withSession = false): array
    {
        $company = Company::create(['code' => 'W9S'.uniqid()[0], 'name' => 'GP Stock W9']);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        foreach (['inventory.view', 'inventory.manage'] as $permCode) {
            $permission = Permission::firstOrCreate(['code' => $permCode], ['name' => $permCode, 'module' => str($permCode)->before('.')]);
        }
        $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => 'inv-w9'], ['name' => 'Inv W9']);
        $role->permissions()->syncWithoutDetaching(Permission::whereIn('code', ['inventory.view', 'inventory.manage'])->pluck('id'));
        $pivot = (int) DB::table('company_user')->where(['company_id' => $company->id, 'user_id' => $user->id])->value('id');
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $pivot, 'role_id' => $role->id]);
        $unit = Unit::create(['company_id' => $company->id, 'code' => 'EA', 'name' => 'Each']);
        $item = Item::create(['company_id' => $company->id, 'unit_id' => $unit->id, 'sku' => 'STK9', 'name' => 'Semen', 'category' => 'material']);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'code' => 'WS', 'name' => 'Gudang Stock']);
        $bin = WarehouseBin::create(['warehouse_id' => $warehouse->id, 'code' => 'B', 'name' => 'B']);
        $balance = StockBalance::create(['company_id' => $company->id, 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'warehouse_bin_id' => $bin->id, 'lot_number' => '', 'quantity' => '10', 'reserved_quantity' => '0', 'damaged_quantity' => '0', 'obsolete_quantity' => '0', 'in_transit_quantity' => '0']);
        if ($withSession) {
            $this->actingAs($user)->withSession(['company_id' => $company->id]);
        }

        return [$balance, $user, $company];
    }
}
