<?php

namespace Tests\Feature\Project;

use App\Models\BoredPile;
use App\Models\BoredPileDrilling;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\ConcreteDelivery;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\PileTest;
use App\Models\Project;
use App\Models\ProjectZone;
use App\Models\Role;
use App\Models\User;
use App\Services\PileRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FoundationControlTest extends TestCase
{
    use RefreshDatabase;

    private $company;

    private $user;

    private $project;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->company, $this->user, $this->project] = $this->fixture();
    }

    private function fixture(): array
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => 'fc-gp'], ['name' => 'FC GP']);
        foreach (['project.view', 'project.manage'] as $perm) {
            $permission = Permission::firstOrCreate(['code' => $perm], ['name' => $perm, 'module' => str($perm)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membership = DB::table('company_user')->where('company_id', $company->id)->where('user_id', $user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C1', 'name' => 'Client']);
        $project = Project::create([
            'company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P-FC', 'name' => 'Proyek FC',
            'status' => 'in_progress', 'overbreak_tolerance_percent' => '10',
        ]);

        return [$company, $user, $project];
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

    public function test_control_tower_renders_kpi_and_grid_without_coordinates(): void
    {
        $this->makePile('BP-01', ['status' => 'drilling', 'actual_depth_m' => '12.5']);
        $this->makePile('BP-02', ['status' => 'completed']);

        $response = $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get("/admin/projects/{$this->project->id}/foundation-control")
            ->assertOk()
            ->assertSee('Foundation Control Tower')
            ->assertSee('Risk Radar')
            ->assertSee('Grid Layout (fallback)')
            ->assertSee('BP-01');

        // Audit terekam.
        $this->assertDatabaseHas('audit_logs', ['event' => 'foundation_control_viewed', 'auditable_id' => $this->project->id]);
    }

    public function test_cross_company_cannot_view_control_tower(): void
    {
        $other = Company::create(['code' => 'GB', 'name' => 'GB']);
        $outsider = User::factory()->create();
        $outsider->companies()->attach($other->id, ['is_default' => true, 'is_active' => true]);
        $roleB = Role::firstOrCreate(['company_id' => $other->id, 'code' => 'fc-b'], ['name' => 'FC B']);
        $permission = Permission::firstOrCreate(['code' => 'project.view'], ['name' => 'project.view', 'module' => 'project']);
        $roleB->permissions()->syncWithoutDetaching([$permission->id]);
        $membershipB = DB::table('company_user')->where('company_id', $other->id)->where('user_id', $outsider->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membershipB->id, 'role_id' => $roleB->id]);

        // Punya permission di company sendiri, tetap 404 — isolasi data, bukan gate permission.
        $this->actingAs($outsider)->withSession(['company_id' => $other->id])
            ->get("/admin/projects/{$this->project->id}/foundation-control")
            ->assertNotFound();
    }

    public function test_risk_engine_flags_failed_test_and_overbreak_as_critical(): void
    {
        $pile = $this->makePile('BP-RISK', [
            'status' => 'testing',
            'actual_depth_m' => '24', // 20% deviation > toleransi 5%
            'overbreak_exceeded' => true,
            'overbreak_percent' => '25.5', // > 2x toleransi proyek (10)
        ]);
        PileTest::create([
            'company_id' => $this->company->id, 'project_id' => $this->project->id, 'bored_pile_id' => $pile->id,
            'number' => 'PIT-F1', 'test_type' => 'PIT', 'scheduled_date' => now()->toDateString(),
            'result_status' => 'failed', 'recorded_by' => $this->user->id,
        ]);

        $risk = app(PileRiskService::class)->evaluate($pile->refresh());
        $codes = collect($risk['reasons'])->pluck('code');
        $this->assertContains('test_failed', $codes);
        $this->assertContains('concrete_overbreak', $codes);
        $this->assertContains('depth_mismatch', $codes);
        $this->assertSame('critical', $risk['level']);
    }

    public function test_risk_engine_healthy_when_no_signals(): void
    {
        $pile = $this->makePile('BP-OK', ['status' => 'drilling', 'actual_depth_m' => '20.2']);

        $risk = app(PileRiskService::class)->evaluate($pile);
        $this->assertSame('healthy', $risk['level']);
        $this->assertSame([], $risk['reasons']);
    }

    public function test_concrete_interruption_detected_from_delivery_gaps(): void
    {
        CompanySetting::put($this->company->id, ['concrete_max_gap_minutes' => '30']);
        $pile = $this->makePile('BP-GAP', ['status' => 'casting']);
        foreach ([['DO-A', '09:00'], ['DO-B', '11:00']] as [$do, $start]) {
            ConcreteDelivery::create([
                'company_id' => $this->company->id, 'project_id' => $this->project->id, 'bored_pile_id' => $pile->id,
                'delivery_order_number' => $do, 'truck_number' => 'T-'.$do, 'grade' => "fc'25",
                'ordered_volume_m3' => '30', 'delivered_volume_m3' => '30', 'accepted_volume_m3' => '30',
                'rejected_volume_m3' => '0', 'slump_cm' => '15', 'status' => 'approved',
                'pour_started_at' => now()->setTimeFromTimeString($start),
                'pour_finished_at' => now()->setTimeFromTimeString($start)->addMinutes(40),
                'recorded_by' => $this->user->id, 'idempotency_key' => $do,
            ]);
        }
        // Gap antara pour_finished DO-A (09:40) dan pour_started DO-B (11:00) = 80 menit > 30.

        $risk = app(PileRiskService::class)->evaluate($pile->refresh());
        $this->assertContains('concrete_interruption', collect($risk['reasons'])->pluck('code'));
        $this->assertSame('watch', $risk['level']);
    }

    public function test_abnormal_duration_requires_enough_project_baseline(): void
    {
        // < 3 baseline drilling → tidak boleh menuduh abnormal.
        $pile = $this->makePile('BP-SLOW', ['status' => 'drilling']);
        BoredPileDrilling::create([
            'company_id' => $this->company->id, 'bored_pile_id' => $pile->id,
            'drilling_started_at' => now()->subHours(50), 'drilling_finished_at' => now(),
            'recorded_by' => $this->user->id, 'status' => 'draft',
        ]);

        $risk = app(PileRiskService::class)->evaluate($pile);
        $this->assertNotContains('abnormal_duration', collect($risk['reasons'])->pluck('code'));
    }
}
