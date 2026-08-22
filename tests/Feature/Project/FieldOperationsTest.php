<?php

namespace Tests\Feature\Project;

use App\Models\BoredPile;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\ConcreteDelivery;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\PileTest;
use App\Models\Project;
use App\Models\ProjectZone;
use App\Models\User;
use App\Services\BoredPileService;
use App\Services\FieldOpsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FieldOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_drilling_record_with_layers_updates_depth_and_requires_independent_verification(): void
    {
        [$company, $recorder, $verifier, $pile] = $this->fixture();
        $service = app(FieldOpsService::class);

        $drilling = $service->recordDrilling($pile, [
            'drilling_started_at' => now()->subHours(6), 'drilling_finished_at' => now(),
            'groundwater_level_m' => '1.800', 'cleaning_method' => 'bentonite', 'sediment_depth_mm' => '45',
            'weather' => 'Cerah',
        ], [
            ['depth_from_m' => '0', 'depth_to_m' => '2.5', 'soil_description' => 'Lempung coklat'],
            ['depth_from_m' => '2.5', 'depth_to_m' => '12.4', 'soil_description' => 'Pasir lepas'],
        ], $recorder);

        $this->assertSame(2, $drilling->layers()->count());
        $this->assertSame('12.400', $pile->refresh()->actual_depth_m);
        $this->assertSame('draft', $drilling->status);

        $this->expectException(ValidationException::class);
        $service->verifyDrilling($drilling, $recorder);
    }

    public function test_verified_drilling_by_different_user(): void
    {
        [$company, $recorder, $verifier, $pile] = $this->fixture();
        $service = app(FieldOpsService::class);
        $drilling = $service->recordDrilling($pile, ['drilling_started_at' => now()], [['depth_from_m' => '0', 'depth_to_m' => '5', 'soil_description' => 'Pasir']], $recorder);

        $verified = $service->verifyDrilling($drilling, $verifier);

        $this->assertSame('verified', $verified->status);
        $this->assertSame($verifier->id, $verified->verified_by);
    }

    public function test_concrete_approval_single_source_updates_pile_once_and_recalculates_overbreak(): void
    {
        [$company, $user, , $pile] = $this->fixture(withTheoretical: true);
        $service = app(FieldOpsService::class);
        $delivery = ConcreteDelivery::create([
            'company_id' => $company->id, 'project_id' => $pile->project_id, 'bored_pile_id' => $pile->id,
            'delivery_order_number' => 'DO-001', 'truck_number' => 'B-1234XYZ', 'grade' => "fc'25",
            'ordered_volume_m3' => '30', 'delivered_volume_m3' => '30.5', 'accepted_volume_m3' => '29.8',
            'rejected_volume_m3' => '0.7', 'slump_cm' => '12', 'status' => 'draft', 'recorded_by' => $user->id,
            'idempotency_key' => 'cd-1',
        ]);

        $approved = $service->approveConcreteDelivery($delivery, $user);
        $again = $service->approveConcreteDelivery($approved->refresh(), $user);

        $this->assertSame($approved->id, $again->id);
        $pile->refresh();
        $this->assertSame('29.8000', (string) $pile->actual_concrete_m3);

        $second = ConcreteDelivery::create([
            'company_id' => $company->id, 'project_id' => $pile->project_id, 'bored_pile_id' => $pile->id,
            'delivery_order_number' => 'DO-002', 'truck_number' => 'B-5678XYZ',
            'ordered_volume_m3' => '10', 'delivered_volume_m3' => '10', 'accepted_volume_m3' => '9.7',
            'status' => 'draft', 'recorded_by' => $user->id, 'idempotency_key' => 'cd-2',
        ]);
        $service->approveConcreteDelivery($second, $user);

        $pile->refresh();
        $this->assertSame('39.5000', (string) $pile->actual_concrete_m3);
        $expectedOverbreak = round((39.5 / 38 - 1) * 100, 3);
        $this->assertEqualsWithDelta($expectedOverbreak, (float) $pile->overbreak_percent, 0.01);
        $this->assertFalse((bool) $pile->overbreak_exceeded);
    }

    public function test_accepted_plus_rejected_cannot_exceed_delivered(): void
    {
        [$company, $user, , $pile] = $this->fixture();
        $service = app(FieldOpsService::class);

        $this->expectException(ValidationException::class);
        $service->recordConcreteDelivery($pile, [
            'delivery_order_number' => 'DO-BAD', 'truck_number' => 'X',
            'ordered_volume_m3' => '10', 'delivered_volume_m3' => '10',
            'accepted_volume_m3' => '9', 'rejected_volume_m3' => '2', 'idempotency_key' => 'cd-bad',
        ], $user);
    }

    public function test_completion_gate_blocks_while_test_scheduled_and_requires_pass_when_enabled(): void
    {
        [, $user, , $pile] = $this->fixture(withStatus: 'testing');
        CompanySetting::put($pile->project->company_id, ['require_pile_test_pass' => '1']);
        $service = app(FieldOpsService::class);

        $test = PileTest::create([
            'company_id' => $pile->project->company_id, 'project_id' => $pile->project_id, 'bored_pile_id' => $pile->id,
            'number' => 'PIT-001', 'test_type' => 'PIT', 'scheduled_date' => today(), 'recorded_by' => $user->id,
        ]);

        $this->expectException(ValidationException::class);
        $service->completionGate($pile);
    }

    public function test_completed_transition_allowed_after_passed_and_consultant_approval(): void
    {
        [$company, $user, , $pile] = $this->fixture(withStatus: 'testing');
        CompanySetting::put($company->id, ['require_pile_test_pass' => '1']);
        $approver = User::factory()->create();
        $approver->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $service = app(FieldOpsService::class);

        $test = PileTest::create([
            'company_id' => $company->id, 'project_id' => $pile->project_id, 'bored_pile_id' => $pile->id,
            'number' => 'PIT-002', 'test_type' => 'PIT', 'scheduled_date' => today(), 'recorded_by' => $user->id,
        ]);
        $service->recordPileTestResult($test, ['result_status' => 'passed', 'interpretation' => 'Integritas baik'], $user);
        $service->approvePileTestResult($test->refresh(), $approver);
        $pile = app(BoredPileService::class)->transition($pile->refresh(), 'completed', $user, 'Uji lulus');

        $this->assertSame('completed', $pile->status);
    }

    public function test_cross_company_service_guard(): void
    {
        [$companyA, $userA, , $pileA] = $this->fixture();
        [$companyB] = $this->fixture(code: 'GB');
        $service = app(FieldOpsService::class);

        $this->expectException(ValidationException::class);
        $service->resolvePile($companyB->id, $pileA->id);
    }

    private function fixture(string $code = 'GP', bool $withTheoretical = false, string $withStatus = 'drilling'): array
    {
        $company = Company::create(['code' => $code, 'name' => $code]);
        $recorder = User::factory()->create();
        $verifier = User::factory()->create();
        foreach ([$recorder, $verifier] as $u) {
            $u->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        }
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C'.$code, 'name' => 'Client']);
        $project = Project::create([
            'company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P-'.$code, 'name' => 'Project '.$code,
            'contract_value' => '1000000000', 'overbreak_tolerance_percent' => '8', 'status' => 'in_progress',
        ]);
        $pile = BoredPile::create([
            'project_id' => $project->id, 'project_zone_id' => ProjectZone::create(['project_id' => $project->id, 'code' => 'Z1', 'name' => 'Zona 1'])->id,
            'pile_number' => 'BP-T1', 'diameter_mm' => '1000', 'planned_depth_m' => '20', 'theoretical_concrete_m3' => $withTheoretical ? '38' : null,
            'status' => $withStatus, 'created_by' => $recorder->id,
        ]);
        FiscalPeriod::create(['company_id' => $company->id, 'name' => 'FY2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);

        return [$company, $recorder, $verifier, $pile];
    }
}
