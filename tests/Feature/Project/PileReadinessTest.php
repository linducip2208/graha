<?php

namespace Tests\Feature\Project;

use App\Models\BoredPile;
use App\Models\BoredPileDrilling;
use App\Models\BoredPileDrillingLayer;
use App\Models\CasingUnit;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\ConstraintLog;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Permission;
use App\Models\PileBottomCleaningInspection;
use App\Models\PileReadinessCheck;
use App\Models\Project;
use App\Models\ProjectZone;
use App\Models\Role;
use App\Models\User;
use App\Services\PileReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PileReadinessTest extends TestCase
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
        $role = Role::firstOrCreate(['company_id' => $this->company->id, 'code' => 'pr-gp'], ['name' => 'PR GP']);
        foreach (['project.view', 'project.manage', 'qms.verify'] as $perm) {
            $permission = Permission::firstOrCreate(['code' => $perm], ['name' => $perm, 'module' => str($perm)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membership = DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', $this->user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);
        $customer = Customer::create(['company_id' => $this->company->id, 'code' => 'C1', 'name' => 'Client']);
        $this->project = Project::create([
            'company_id' => $this->company->id, 'customer_id' => $customer->id, 'code' => 'P-RD', 'name' => 'Proyek Readiness',
            'status' => 'in_progress', 'overbreak_tolerance_percent' => '10',
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

    public function test_drill_readiness_not_ready_with_blockers_then_ready_after_data_complete(): void
    {
        $pile = $this->makePile('BP-DR');
        $service = app(PileReadinessService::class);

        // Data minimal → NOT_READY dengan blocker terstruktur.
        $result = $service->drillReadiness($pile);
        $this->assertSame(PileReadinessService::DRILL_NOT_READY, $result['status']);
        $this->assertNotEmpty($result['blockers']);
        $failKeys = collect($result['checklist'])->where('state', 'fail')->pluck('key');
        $this->assertContains('setting_out_complete', $failKeys);
        $this->assertContains('survey_coordinates', $failKeys);
        $this->assertContains('rig_allocated', $failKeys);

        // Fitur opsional non-aktif → state skip (bukan fail): casing/JSA.
        $skipKeys = collect($result['checklist'])->where('state', 'skip')->pluck('key');
        $this->assertContains('casing_available', $skipKeys);
        $this->assertContains('jsa_complete', $skipKeys);

        // Lengkapi data → READY.
        $pile->activities()->create(['from_status' => 'planned', 'to_status' => 'setting_out', 'started_at' => now()->subHour(), 'finished_at' => now(), 'recorded_by' => $this->user->id]);
        $pile->update([
            'coordinate_x' => '500.0000', 'coordinate_y' => '1200.0000',
            'platform_ready_at' => now(), 'operator_name' => 'Sukarno', 'rig_equipment_id' => null,
        ]);
        $equipment = Equipment::create(['company_id' => $this->company->id, 'code' => 'EQ-RD', 'name' => 'Rig RD', 'category' => 'rig', 'ownership' => 'owned', 'status' => 'operational']);
        $pile->update(['rig_equipment_id' => $equipment->id]);

        $ready = $service->drillReadiness($pile->refresh());
        $stillFail = collect($ready['checklist'])->where('state', 'fail')->pluck('key');
        // approved_drawing tetap fail (belum ada dokumen gambar) — sisanya lolos.
        $this->assertSame(['approved_drawing'], $stillFail->all());

        // Daftarkan + approve drawing document → READY penuh.
        $document = Document::create([
            'company_id' => $this->company->id, 'project_id' => $this->project->id, 'document_type' => 'shop_drawing',
            'number' => 'DWG-001', 'title' => 'Shop Drawing BP', 'owner_id' => $this->user->id, 'workflow_status' => 'approved',
        ]);
        $document->save();

        $full = $service->drillReadiness($pile->refresh());
        $this->assertSame(PileReadinessService::DRILL_READY, $full['status']);
        $this->assertSame([], $full['blockers']);
    }

    public function test_drill_readiness_blocked_by_open_constraint_and_hold(): void
    {
        $pile = $this->makePile('BP-HOLD', ['status' => 'hold']);
        $result = app(PileReadinessService::class)->drillReadiness($pile);
        $failKeys = collect($result['checklist'])->where('state', 'fail')->pluck('key');
        $this->assertContains('no_blocking_hold', $failKeys);

        $pile2 = $this->makePile('BP-CONSTR', ['status' => 'planned']);
        ConstraintLog::create([
            'company_id' => $this->company->id, 'project_id' => $this->project->id, 'type' => 'material',
            'title' => 'Material besi tertunda', 'description' => 'Besi belum datang', 'status' => 'open',
            'raised_at' => now()->toDateString(), 'recorded_by' => $this->user->id,
        ]);
        $result2 = app(PileReadinessService::class)->drillReadiness($pile2);
        $this->assertContains('no_blocking_hold', collect($result2['checklist'])->where('state', 'fail')->pluck('key'));
    }

    public function test_cast_readiness_gates_depth_bore_log_cleaning(): void
    {
        $pile = $this->makePile('BP-CAST');
        $service = app(PileReadinessService::class);

        $blocked = $service->castReadiness($pile);
        $this->assertSame(PileReadinessService::CAST_BLOCKED, $blocked['status']);
        $failKeys = collect($blocked['checklist'])->where('state', 'fail')->pluck('key');
        $this->assertContains('target_depth', $failKeys);
        $this->assertContains('bore_log', $failKeys);
        $this->assertContains('cage_delivered', $failKeys);
        $this->assertContains('concrete_booking', $failKeys);

        // Depth dalam toleransi + bore log + sediment tercatat + booking.
        $drilling = BoredPileDrilling::create([
            'company_id' => $this->company->id, 'bored_pile_id' => $pile->id,
            'drilling_started_at' => now()->subDay(), 'sediment_depth_mm' => '30',
            'recorded_by' => $this->user->id, 'status' => 'verified',
        ]);
        BoredPileDrillingLayer::create(['bored_pile_drilling_id' => $drilling->id, 'sequence' => 1, 'depth_from_m' => '0', 'depth_to_m' => '20.1', 'soil_description' => 'Pasir lepas']);
        $pile->update(['actual_depth_m' => '19.7', 'concrete_booking_confirmed_at' => now()]); // toleransi default 5% dari 20 m = 19 m

        $midway = $service->castReadiness($pile->refresh());
        $remaining = collect($midway['checklist'])->where('state', 'fail')->pluck('key');
        $this->assertNotContains('target_depth', $remaining);
        $this->assertNotContains('bore_log', $remaining);
        $this->assertContains('cage_delivered', $remaining); // cage belum delivered
    }

    public function test_bottom_cleaning_gate_only_when_enabled_and_acceptance_required(): void
    {
        $pile = $this->makePile('BP-CLEAN', ['actual_depth_m' => '20']);
        $service = app(PileReadinessService::class);

        // Gate OFF: tanpa record cleaning pun checklist tidak fail karena gate.
        CompanySetting::put($this->company->id, ['require_cleaning_inspection' => '0']);

        // Gate ON tanpa record → fail.
        CompanySetting::put($this->company->id, ['require_cleaning_inspection' => '1']);
        $gatedOff = $service->castReadiness($pile);
        $this->assertContains('bottom_cleaning', collect($gatedOff['checklist'])->where('state', 'fail')->pluck('key'));

        // Record pending → masih fail; accepted → pass.
        $inspection = PileBottomCleaningInspection::create([
            'company_id' => $this->company->id, 'bored_pile_id' => $pile->id,
            'method' => 'airlift', 'sediment_thickness_mm' => '25', 'inspected_at' => now(),
            'inspected_by' => $this->user->id, 'status' => 'pending',
        ]);
        $pending = $service->castReadiness($pile->refresh());
        $this->assertContains('bottom_cleaning', collect($pending['checklist'])->where('state', 'fail')->pluck('key'));

        $inspection->update(['status' => 'accepted']);
        $accepted = $service->castReadiness($pile->refresh());
        $this->assertNotContains('bottom_cleaning', collect($accepted['checklist'])->where('state', 'fail')->pluck('key'));
    }

    public function test_sediment_tolerance_only_enforced_when_configured(): void
    {
        $pile = $this->makePile('BP-SED', ['actual_depth_m' => '20']);
        PileBottomCleaningInspection::create([
            'company_id' => $this->company->id, 'bored_pile_id' => $pile->id,
            'method' => 'airlift', 'sediment_thickness_mm' => '80', 'inspected_at' => now(),
            'inspected_by' => $this->user->id, 'status' => 'accepted',
        ]);
        $service = app(PileReadinessService::class);

        // Default OFF → skip.
        $off = $service->castReadiness($pile);
        $sedimentCheck = collect($off['checklist'])->firstWhere('key', 'sediment_tolerance');
        $this->assertSame('skip', $sedimentCheck['state']);

        // ON dengan 80 mm > 50 mm → fail.
        CompanySetting::put($this->company->id, ['sediment_max_mm' => '50']);
        $on = $service->castReadiness($pile);
        $this->assertContains('sediment_tolerance', collect($on['checklist'])->where('state', 'fail')->pluck('key'));
    }

    public function test_record_check_persists_snapshot_and_http_endpoint_works(): void
    {
        $pile = $this->makePile('BP-SNAP');
        $check = app(PileReadinessService::class)->recordCheck($pile, PileReadinessCheck::KIND_DRILL, $this->user);
        $this->assertDatabaseHas('pile_readiness_checks', [
            'bored_pile_id' => $pile->id, 'kind' => 'drill', 'status' => PileReadinessService::DRILL_NOT_READY,
        ]);

        $response = $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post("/admin/bored-piles/{$pile->id}/readiness-check", ['kind' => 'cast'])
            ->assertRedirect();
        $this->assertDatabaseHas('pile_readiness_checks', ['bored_pile_id' => $pile->id, 'kind' => 'cast']);

        // Passport menampilkan kartu readiness.
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get("/admin/bored-piles/{$pile->id}/passport")
            ->assertOk()
            ->assertSee('Ready to Drill')
            ->assertSee('Ready to Cast')
            ->assertSee('NOT READY');
    }

    public function test_attestation_endpoints_set_timestamps_with_audit(): void
    {
        $pile = $this->makePile('BP-ATT');
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post("/admin/bored-piles/{$pile->id}/attest", ['attestation' => 'platform'])
            ->assertRedirect();
        $this->assertNotNull($pile->refresh()->platform_ready_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'pile_attestation_recorded', 'auditable_id' => $pile->id]);
    }

    public function test_casing_required_blocks_when_no_unit_available(): void
    {
        $pile = $this->makePile('BP-CSG', ['casing_required' => true]);
        $service = app(PileReadinessService::class);

        $without = $service->drillReadiness($pile);
        $this->assertContains('casing_available', collect($without['checklist'])->where('state', 'fail')->pluck('key'));

        CasingUnit::create([
            'company_id' => $this->company->id, 'code' => 'CS-01', 'diameter_mm' => '1000', 'length_m' => '12',
            'ownership' => 'owned', 'condition' => 'good', 'status' => 'in_stock', 'created_by' => $this->user->id,
        ]);
        $with = $service->drillReadiness($pile->refresh());
        $this->assertNotContains('casing_available', collect($with['checklist'])->where('state', 'fail')->pluck('key'));
    }
}
