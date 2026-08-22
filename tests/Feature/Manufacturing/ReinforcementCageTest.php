<?php

namespace Tests\Feature\Manufacturing;

use App\Models\BoredPile;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectZone;
use App\Models\User;
use App\Services\BoredPileService;
use App\Services\ReinforcementCageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReinforcementCageTest extends TestCase
{
    use RefreshDatabase;

    public function test_qc_requires_weight_within_tolerance_and_independent_inspector(): void
    {
        [$company, $maker, $inspector, $pile] = $this->fixture();
        $service = app(ReinforcementCageService::class);

        $cage = $service->create($company->id, [
            'number' => 'CAGE-1', 'diameter_mm' => '1000', 'total_length_m' => '20',
            'theoretical_weight_kg' => '2000',
        ], $maker);

        try {
            $service->recordQc($cage, true, null, $maker);
            $this->fail('Pembuat tidak boleh QC sendiri.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('qc', $e->errors());
        }

        $cage->update(['actual_weight_kg' => '2500']);
        try {
            $service->recordQc($cage->refresh(), true, null, $inspector);
            $this->fail('Varians 25% harus ditolak (toleransi default 5%).');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('actual_weight_kg', $e->errors());
        }

        $cage->update(['actual_weight_kg' => '2050']);
        $passed = $service->recordQc($cage->refresh(), true, 'OK', $inspector);
        $this->assertSame('passed', $passed->qc_status);
    }

    public function test_delivery_requires_passed_qc_and_ready_pile(): void
    {
        [$company, $maker, $inspector, $pile] = $this->fixture(pileStatus: 'cleaning');
        $service = app(ReinforcementCageService::class);
        $cage = $service->create($company->id, ['number' => 'CAGE-2', 'diameter_mm' => '800', 'total_length_m' => '18', 'theoretical_weight_kg' => '1500'], $maker);

        try {
            $service->deliverToPile($cage, $pile, $inspector);
            $this->fail('Cage tanpa QC lolos tidak boleh dikirim.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('qc', $e->errors());
        }

        $cage->update(['actual_weight_kg' => '1520']);
        $service->recordQc($cage->refresh(), true, null, $inspector);
        $delivered = $service->deliverToPile($cage->refresh(), $pile, $inspector);
        $this->assertSame($pile->id, $delivered->bored_pile_id);
        $this->assertNotNull($delivered->delivered_at);
    }

    public function test_installation_gate_blocks_when_flag_enabled_without_delivered_cage(): void
    {
        [$company, $maker, $inspector, $pile] = $this->fixture(pileStatus: 'inspection');
        CompanySetting::put($company->id, ['require_cage_passed' => '1']);

        $this->expectException(ValidationException::class);
        app(BoredPileService::class)->transition($pile->refresh(), 'cage_installation', $maker);
    }

    public function test_installation_allowed_after_cage_delivered_when_flag_enabled(): void
    {
        [$company, $maker, $inspector, $pile] = $this->fixture(pileStatus: 'inspection');
        CompanySetting::put($company->id, ['require_cage_passed' => '1']);
        $service = app(ReinforcementCageService::class);

        $cage = $service->create($company->id, ['number' => 'CAGE-3', 'diameter_mm' => '1000', 'total_length_m' => '20', 'theoretical_weight_kg' => '2000'], $maker);
        $cage->update(['actual_weight_kg' => '2010']);
        $service->recordQc($cage->refresh(), true, null, $inspector);
        $service->deliverToPile($cage->refresh(), $pile->refresh(), $maker);

        $pile = app(BoredPileService::class)->transition($pile->refresh(), 'cage_installation', $maker);
        $this->assertSame('cage_installation', $pile->status);
    }

    private function fixture(string $pileStatus = 'drilling'): array
    {
        $company = Company::create(['code' => 'CG'.rand(10, 99), 'name' => 'CG']);
        $maker = User::factory()->create();
        $inspector = User::factory()->create();
        foreach ([$maker, $inspector] as $u) {
            $u->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        }
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C', 'name' => 'Client']);
        $project = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P', 'name' => 'Proyek', 'status' => 'in_progress']);
        $zone = ProjectZone::create(['project_id' => $project->id, 'code' => 'Z1', 'name' => 'Zona 1']);
        $pile = BoredPile::create([
            'project_id' => $project->id, 'project_zone_id' => $zone->id, 'pile_number' => 'BP-CG',
            'diameter_mm' => '1000', 'planned_depth_m' => '20', 'status' => $pileStatus, 'created_by' => $maker->id,
        ]);

        return [$company, $maker, $inspector, $pile];
    }
}
