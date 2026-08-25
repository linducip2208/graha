<?php

namespace Tests\Feature\Hse;

use App\Models\BoredPile;
use App\Models\CalibrationRecord;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\HseExposureLog;
use App\Models\HseIncident;
use App\Models\Permission;
use App\Models\PpeIssuance;
use App\Models\Project;
use App\Models\ProjectZone;
use App\Models\Role;
use App\Models\SafetyObservation;
use App\Models\User;
use App\Services\HseMetricsService;
use App\Services\ItpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

class HseQmsBacklogWave2Test extends TestCase
{
    use RefreshDatabase;

    public function test_safety_observation_resolve_and_company_isolation(): void
    {
        [$companyA, $companyB, $reporter, $verifier] = $this->fixture();
        $observation = SafetyObservation::create(['company_id' => $companyA->id, 'number' => 'OBS-1', 'category' => 'unsafe_condition', 'observed_at' => now(), 'location' => 'Workshop', 'description' => 'Kabel listrik terkelupas', 'status' => 'open', 'reported_by' => $reporter->id]);

        // Verifier menutup dengan catatan resolusi.
        $observation->update(['status' => 'resolved', 'resolution_notes' => 'Kabel diganti', 'resolved_by' => $verifier->id, 'resolved_at' => now()]);
        $this->assertSame('resolved', $observation->refresh()->status);
        $this->assertSame($verifier->id, $observation->resolved_by);

        // Isolasi: observasi company A tidak muncul pada company B.
        $this->assertSame(0, SafetyObservation::where('company_id', $companyB->id)->count());
    }

    public function test_calibration_status_classification(): void
    {
        [$company, , $owner] = $this->fixture();
        $equipment = Equipment::create(['company_id' => $company->id, 'code' => 'EQ-1', 'name' => 'Rig', 'ownership' => 'owned', 'category' => 'drilling', 'current_hour_meter' => '0']);

        $overdue = CalibrationRecord::create(['company_id' => $company->id, 'equipment_id' => $equipment->id, 'instrument_name' => 'Thermometer', 'calibrated_at' => now()->subMonths(14)->toDateString(), 'next_due_at' => now()->subDays(5)->toDateString(), 'created_by' => $owner->id]);
        $dueSoon = CalibrationRecord::create(['company_id' => $company->id, 'equipment_id' => $equipment->id, 'instrument_name' => 'Slump cone', 'calibrated_at' => now()->subMonths(11)->toDateString(), 'next_due_at' => now()->addDays(10)->toDateString(), 'created_by' => $owner->id]);
        $ok = CalibrationRecord::create(['company_id' => $company->id, 'equipment_id' => $equipment->id, 'instrument_name' => 'Pressure gauge', 'calibrated_at' => now()->toDateString(), 'next_due_at' => now()->addYear()->toDateString(), 'result' => 'adjust', 'created_by' => $owner->id]);

        $this->assertSame('overdue', $overdue->statusNow());
        $this->assertSame('due_soon', $dueSoon->statusNow());
        $this->assertSame('ok', $ok->statusNow());
    }

    public function test_itp_independence_fail_notes_and_hold_point_gate(): void
    {
        [$company, , $preparer, $inspector, $project] = $this->fixture();
        $zone = ProjectZone::create(['project_id' => $project->id, 'code' => 'Z1', 'name' => 'Zona 1']);
        $pile = BoredPile::create(['project_id' => $project->id, 'project_zone_id' => $zone->id, 'pile_number' => 'P-01', 'diameter_mm' => '800', 'planned_depth_m' => '20.000', 'status' => 'planned', 'created_by' => $preparer->id]);
        $service = app(ItpService::class);

        $plan = $service->createPlan($project, $pile, [
            'company_id' => $company->id,
            'title' => 'ITP Drilling',
            'items' => [
                ['stage' => 'Set-up rig', 'method' => 'Visual vertikalitas', 'acceptance_criteria' => 'Deviasi <= 1%', 'checkpoint_type' => 'hold'],
                ['stage' => 'Bore log', 'method' => 'Pencatatan lapisan', 'acceptance_criteria' => 'Sesuai prediksi', 'checkpoint_type' => 'witness'],
            ],
        ], $preparer);

        $this->assertStringContainsString('ITP', (string) $plan->number);
        $this->assertSame(2, $plan->items()->count());
        $holdItem = $plan->items()->where('checkpoint_type', 'hold')->first();

        try {
            $service->recordInspection($holdItem, today()->toDateString(), 'fail', null, null, $company->id, $inspector, $preparer);
            $this->fail('Fail tanpa temuan harus ditolak.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        try {
            $service->recordInspection($holdItem, today()->toDateString(), 'pass', 'OK', null, $company->id, $preparer, $preparer);
            $this->fail('Verifikasi mandiri (pemeriksa = perekam) harus ditolak.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $service->recordInspection($holdItem, today()->toDateString(), 'pending', null, null, $company->id, $inspector, $preparer);
        $this->assertCount(1, $service->openHoldPoints($plan->refresh()), 'Pending membuat hold point tetap terbuka.');

        $service->recordInspection($holdItem, today()->toDateString(), 'pass', '0.8% deviasi', null, $company->id, $inspector, $preparer);
        $this->assertCount(0, $service->openHoldPoints($plan->refresh()));
        $plan->update(['status' => 'closed']);
        $this->assertSame('closed', $plan->refresh()->status);

        $witness = $plan->items()->where('checkpoint_type', 'witness')->first();
        try {
            $service->recordInspection($witness, today()->toDateString(), 'pending', null, null, $company->id, $inspector, $preparer);
            $this->fail('Inspeksi pada ITP closed harus ditolak.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_hse_metrics_fr_sr_from_real_exposure(): void
    {
        [$company, , $owner, , $project] = $this->fixture();
        HseExposureLog::create(['company_id' => $company->id, 'period_month' => '2026-08-01', 'man_hours' => '24000', 'avg_headcount' => 30, 'created_by' => $owner->id]);
        HseIncident::create(['company_id' => $company->id, 'project_id' => $project->id, 'number' => 'INC-LT', 'type' => 'incident', 'severity' => 'high', 'is_lost_time' => true, 'lost_days' => 4, 'occurred_at' => '2026-08-05 09:00:00', 'location' => 'Site A', 'description' => 'Terjatuh dari platform', 'reported_by' => $owner->id]);
        HseIncident::create(['company_id' => $company->id, 'project_id' => $project->id, 'number' => 'INC-NM', 'type' => 'near_miss', 'severity' => 'low', 'occurred_at' => '2026-08-06 10:00:00', 'location' => 'Site A', 'description' => 'Material hampir jatuh', 'reported_by' => $owner->id]);

        $summary = app(HseMetricsService::class)->summary($company->id, '2026-08-01', '2026-08-31');

        // FR = 1 × 1.000.000 / 24.000 jam ≈ 41.67 (bcmath memotong: 41.66); SR = 4 × 1.000.000 / 24.000 = 166.67.
        $this->assertSame('24000.00', $summary['man_hours']);
        $this->assertSame(1, $summary['lost_time_incidents']);
        $this->assertSame('41.66', $summary['fr']);
        $this->assertSame('166.66', $summary['sr']);
        $this->assertSame('41.66', $summary['trir'], 'Recordable = severity high/fatal.');
        $this->assertSame(2, $summary['total_incidents']);
    }

    public function test_metrics_require_exposure_log(): void
    {
        [$company] = $this->fixture();
        $this->expectException(InvalidArgumentException::class);
        app(HseMetricsService::class)->summary($company->id, '2026-08-01', '2026-08-31');
    }

    public function test_ppe_issuance_return_flow(): void
    {
        [$company, , $owner] = $this->fixture();
        $worker = User::factory()->create();
        $issuance = PpeIssuance::create(['company_id' => $company->id, 'user_id' => $worker->id, 'item_name' => 'Harness', 'size' => 'L', 'quantity' => 1, 'issued_at' => '2026-08-01', 'condition_out' => 'good', 'issued_by' => $owner->id]);
        $this->assertNull($issuance->returned_at);
        $issuance->update(['returned_at' => '2026-08-20', 'condition_in' => 'worn']);
        $this->assertSame('2026-08-20', $issuance->refresh()->returned_at->toDateString());
        $this->assertSame('worn', $issuance->condition_in);
    }

    public function test_new_pages_render_for_permitted_user(): void
    {
        [$company, , $owner] = $this->fixture();
        $role = Role::create(['company_id' => $company->id, 'code' => 'qa-hse', 'name' => 'QA HSE']);
        foreach (['manufacturing.view', 'manufacturing.manage', 'qms.view', 'qms.manage', 'hse.view', 'hse.manage', 'hse.verify'] as $code) {
            $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => str($code)->before('.')])->id]);
        }
        $membershipId = (int) \DB::table('company_user')->where(['company_id' => $company->id, 'user_id' => $owner->id])->value('id');
        \DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membershipId, 'role_id' => $role->id]);

        $this->actingAs($owner)->withSession(['company_id' => $company->id]);
        $this->get('/admin/calibrations')->assertOk();
        $this->get('/admin/itps')->assertOk();
        $this->get('/admin/hse?tab=observe')->assertOk();
        $this->get('/admin/hse/metrics')->assertOk();

        // POST kalibrasi valid.
        $equipment = Equipment::create(['company_id' => $company->id, 'code' => 'EQ-SM', 'name' => 'Rig', 'ownership' => 'owned', 'category' => 'drilling', 'current_hour_meter' => '0']);
        $this->post('/admin/calibrations', ['equipment_id' => $equipment->id, 'instrument_name' => 'Torque meter', 'calibrated_at' => '2026-08-01', 'next_due_at' => '2027-08-01', 'result' => 'pass'])->assertRedirect();
        $this->assertDatabaseHas('calibration_records', ['company_id' => $company->id, 'instrument_name' => 'Torque meter']);
    }

    /** @return array [companyA, companyB, ownerUser, verifierUser, project] */
    private function fixture(): array
    {
        $companyA = Company::create(['code' => 'GPW'.random_int(10, 99), 'name' => 'GP A']);
        $companyB = Company::create(['code' => 'GPX'.random_int(10, 99), 'name' => 'GP B']);
        $user = User::factory()->create();
        $user->companies()->attach([$companyA->id => ['is_default' => true, 'is_active' => true]]);
        $customer = Customer::create(['company_id' => $companyA->id, 'code' => 'C-1', 'name' => 'Pelanggan']);
        $project = Project::create(['company_id' => $companyA->id, 'customer_id' => $customer->id, 'code' => 'P-1', 'name' => 'Proyek', 'contract_value' => '100000000', 'status' => 'in_progress']);

        return [$companyA, $companyB, $user, User::factory()->create(), $project];
    }
}
