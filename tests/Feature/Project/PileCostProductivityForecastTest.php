<?php

namespace Tests\Feature\Project;

use App\Models\BoredPile;
use App\Models\BoredPileDrilling;
use App\Models\Company;
use App\Models\ConcreteDelivery;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Item;
use App\Models\Permission;
use App\Models\PileTest;
use App\Models\Project;
use App\Models\ProjectZone;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\FoundationForecastService;
use App\Services\FoundationProductivityService;
use App\Services\PileCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PileCostProductivityForecastTest extends TestCase
{
    use RefreshDatabase;

    private $company;

    private $user;

    private $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $this->user = User::factory()->create();
        $this->user->companies()->attach($this->company->id, ['is_default' => true, 'is_active' => true]);
        $role = Role::firstOrCreate(['company_id' => $this->company->id, 'code' => 'cp-gp'], ['name' => 'CP GP']);
        foreach (['project.view', 'project.manage'] as $perm) {
            $permission = Permission::firstOrCreate(['code' => $perm], ['name' => $perm, 'module' => str($perm)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membership = DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', $this->user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);
        $customer = Customer::create(['company_id' => $this->company->id, 'code' => 'C1', 'name' => 'Client']);
        $this->project = Project::create([
            'company_id' => $this->company->id, 'customer_id' => $customer->id, 'code' => 'P-CF', 'name' => 'Proyek Cost Forecast',
            'status' => 'in_progress', 'overbreak_tolerance_percent' => '8',
        ]);
    }

    private function makePile(string $number, array $overrides = []): BoredPile
    {
        return BoredPile::create([
            'project_id' => $this->project->id,
            'project_zone_id' => ProjectZone::firstOrCreate(['project_id' => $this->project->id, 'code' => 'A'], ['name' => 'Zona A'])->id,
            'pile_number' => $number, 'diameter_mm' => '1000', 'planned_depth_m' => '20',
            'created_by' => $this->user->id,
            ...$overrides,
        ]);
    }

    public function test_pile_cost_from_actual_transactions_with_rework_attribution(): void
    {
        // Teoretis beton pile Ø1000 × 20 m = 15.708 m³.
        $pile = $this->makePile('BP-COST', ['actual_depth_m' => '20']);
        $vendor = Vendor::create(['company_id' => $this->company->id, 'code' => 'V-RMX', 'name' => 'Ready Mix']);
        $item = Unit::create(['company_id' => $this->company->id, 'code' => 'M3', 'name' => 'Meter Kubik']);
        $beton = Item::create(['company_id' => $this->company->id, 'sku' => 'BETON-FC25', 'name' => 'Beton fc25', 'category' => 'Material', 'unit_id' => $item->id]);
        $po = PurchaseOrder::create([
            'company_id' => $this->company->id, 'vendor_id' => $vendor->id, 'number' => 'PO-RMX-01', 'order_date' => now()->toDateString(),
            'currency' => 'IDR', 'status' => 'issued', 'total' => '0', 'created_by' => $this->user->id,
        ]);
        PurchaseOrderItem::create(['purchase_order_id' => $po->id, 'item_id' => $beton->id, 'quantity' => '100', 'unit_price' => '1000000']);

        // DO-1 normal (10 m³), DO-2 overbreak (8 m³ di atas teoretis kumulatif).
        foreach ([['DO-1', '10'], ['DO-2', '8']] as [$doNumber, $vol]) {
            ConcreteDelivery::create([
                'company_id' => $this->company->id, 'project_id' => $this->project->id, 'bored_pile_id' => $pile->id,
                'delivery_order_number' => $doNumber, 'truck_number' => 'T'.$doNumber, 'grade' => "fc'25",
                'ordered_volume_m3' => $vol, 'delivered_volume_m3' => $vol, 'accepted_volume_m3' => $vol,
                'rejected_volume_m3' => '0', 'status' => 'approved', 'arrived_at' => now(),
                'recorded_by' => $this->user->id, 'idempotency_key' => $doNumber, 'purchase_order_id' => $po->id,
            ]);
        }
        $pile->update(['actual_concrete_m3' => '18', 'theoretical_concrete_m3' => '15.708', 'overbreak_exceeded' => true]);

        // Material issue langsung ke pile: 2 unit × 50.000 = 100.000.
        $warehouse = Warehouse::create(['company_id' => $this->company->id, 'code' => 'WH-01', 'name' => 'Gudang']);
        StockMovement::create([
            'transaction_id' => '11111111-1111-1111-1111-111111111111', 'company_id' => $this->company->id,
            'item_id' => $beton->id, 'warehouse_id' => $warehouse->id, 'bored_pile_id' => $pile->id, 'movement_type' => 'issue',
            'quantity' => '2', 'balance_after' => '98', 'unit_cost' => '50000', 'reference_type' => 'material_request',
            'reference_id' => '1', 'idempotency_key' => 'sm-test-issue-1', 'posted_by' => $this->user->id, 'posted_at' => now(),
        ]);

        // Testing invoice aktual.
        PileTest::create([
            'company_id' => $this->company->id, 'project_id' => $this->project->id, 'bored_pile_id' => $pile->id,
            'number' => 'PIT-C1', 'test_type' => 'PIT', 'scheduled_date' => now()->toDateString(),
            'result_status' => 'passed', 'recorded_by' => $this->user->id, 'cost_amount' => '7500000',
        ]);

        $cost = app(PileCostService::class)->pileCost($pile);

        // Concrete: 18 m³ × 1.000.000 = 18.000.000 (semua berharga PO).
        $this->assertSame('18000000.00', (string) $cost['concrete_cost']);
        // Extra concrete = 18 - 15.708 = 2.292 m³ × 1jt = 2.292.000 masuk rework.
        $this->assertEqualsWithDelta(2292000.0, (float) $cost['rework_breakdown']['extra_concrete'], 1);
        $this->assertEqualsWithDelta(7500000.0, (float) $cost['testing_cost'], 0.01);
        // Material issue dari stock movement nyata.
        $this->assertEqualsWithDelta(100000.0, (float) $cost['material_issue_cost'], 0.01);
        // Total > rework; untraced kosong karena PO tertaut.
        $this->assertSame('0', (string) $cost['untraced_concrete_volume_m3']);
        $this->assertGreaterThan((float) $cost['rework_cost'], (float) $cost['actual_cost']);
    }

    public function test_untracked_concrete_reported_not_fabricated(): void
    {
        $pile = $this->makePile('BP-NOTRACE', ['actual_depth_m' => '10']);
        ConcreteDelivery::create([
            'company_id' => $this->company->id, 'project_id' => $this->project->id, 'bored_pile_id' => $pile->id,
            'delivery_order_number' => 'DO-X', 'truck_number' => 'T1', 'grade' => "fc'25",
            'ordered_volume_m3' => '5', 'delivered_volume_m3' => '5', 'accepted_volume_m3' => '5',
            'rejected_volume_m3' => '0', 'status' => 'approved', 'arrived_at' => now(),
            'recorded_by' => $this->user->id, 'idempotency_key' => 'DO-X',
        ]);

        $cost = app(PileCostService::class)->pileCost($pile);
        $this->assertSame('5.0000', (string) $cost['untraced_concrete_volume_m3']); // tanpa PO → dilaporkan, bukan dikarang harganya
        $this->assertSame('0.00', (string) $cost['concrete_cost']);
    }

    public function test_redrilling_hours_attributed_to_rework(): void
    {
        $rig = Equipment::create(['company_id' => $this->company->id, 'code' => 'EQ-RG', 'name' => 'Rig', 'category' => 'rig', 'ownership' => 'owned', 'status' => 'operational']);
        $pile = $this->makePile('BP-REDRILL', ['rig_equipment_id' => $rig->id]);
        foreach ([0, 1] as $i) {
            BoredPileDrilling::create([
                'company_id' => $this->company->id, 'bored_pile_id' => $pile->id,
                'drilling_started_at' => now()->subDays($i * 2)->setHour(8),
                'drilling_finished_at' => now()->subDays($i * 2)->setHour(12),
                'recorded_by' => $this->user->id, 'status' => 'verified',
            ]);
        }

        $cost = app(PileCostService::class)->pileCost($pile);
        $this->assertSame('8.000', (string) $cost['rig_hours']); // 2 record × 4 jam
        $this->assertSame('4.000', (string) $cost['rework_breakdown']['redrill_hours']); // record ke-2 = redrill (4 jam)
        // Tanpa rate equipment (tidak ada meter/fuel history) → biaya tidak dikarang.
        $this->assertNull($cost['rig_rate_per_hour']);
    }

    public function test_productivity_metrics_computed_from_real_activity(): void
    {
        $service = app(FoundationProductivityService::class);
        $metrics = $service->projectMetrics($this->project, now()->subDays(14), now());
        $this->assertArrayHasKey('meters_per_day', $metrics);
        $this->assertArrayHasKey('phase_hours', $metrics);

        // Dengan data drilling nyata → meters & phase_hours terisi.
        $pile = $this->makePile('BP-PROD');
        BoredPileDrilling::create([
            'company_id' => $this->company->id, 'bored_pile_id' => $pile->id,
            'drilling_started_at' => now()->subDay()->setHour(8), 'drilling_finished_at' => now()->subDay()->setHour(17),
            'recorded_by' => $this->user->id, 'status' => 'verified',
        ]);
        DB::table('bored_pile_drilling_layers')->insert([
            'bored_pile_drilling_id' => 1, 'sequence' => 1, 'depth_from_m' => '0', 'depth_to_m' => '21.4', 'soil_description' => 'Pasir',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $withData = $service->projectMetrics($this->project, now()->subDays(14), now());
        $this->assertEqualsWithDelta(9.0, (float) $withData['phase_hours']['drilling'], 0.1);
        $this->assertGreaterThan(0, (float) $withData['meters']);
    }

    public function test_forecast_insufficient_then_sufficient(): void
    {
        $service = app(FoundationForecastService::class);
        $insufficient = $service->forecast($this->project);
        $this->assertSame('insufficient_history', $insufficient['method']);
        $this->assertSame('insufficient', $insufficient['confidence']);
        $this->assertNull($insufficient['forecast_completion_date']);

        // Selesaikan beberapa pile dalam window 7 hari → forecast terbentuk.
        for ($i = 1; $i <= 4; $i++) {
            $pile = $this->makePile(sprintf('BP-F%02d', $i));
            $pile->activities()->create(['from_status' => 'planned', 'to_status' => 'completed', 'started_at' => now()->subDays($i), 'finished_at' => now(), 'recorded_by' => $this->user->id]);
            $pile->update(['status' => 'completed']);
        }

        $forecast = $service->forecast($this->project->refresh());
        $this->assertNotSame('insufficient_history', $forecast['method']);
        $this->assertNotNull($forecast['forecast_completion_date']);
        $this->assertContains($forecast['confidence'], ['low', 'medium', 'high']);
    }

    public function test_control_tower_renders_advanced_kpis_and_filter(): void
    {
        $this->makePile('BP-TWR');
        $session = ['company_id' => $this->company->id];
        $url = '/admin/projects/'.$this->project->id.'/foundation-control';

        $this->actingAs($this->user)->withSession($session)
            ->get($url)
            ->assertOk()
            ->assertSee('Advanced Control Tower')
            ->assertSee('Ready to Drill')
            ->assertSee('Rework Cost')
            ->assertSee('Lookahead 3 / 7 Hari')
            ->assertSee('Forecast Finish');

        $this->actingAs($this->user)->withSession($session)
            ->get($url.'?filter=ready_drill')
            ->assertOk()
            ->assertSee('Filter aktif');
    }
}
