<?php

namespace Tests\Feature\Equipment;

use App\Models\Company;
use App\Models\Equipment;
use App\Models\User;
use App\Services\EquipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EquipmentFuelTest extends TestCase
{
    use RefreshDatabase;

    public function test_hour_meter_cannot_move_backward_and_fuel_anomaly_is_flagged(): void
    {
        $c = Company::create(['code' => 'GP', 'name' => 'GP']);
        $u = User::factory()->create();
        $e = Equipment::create(['company_id' => $c->id, 'code' => 'RIG-1', 'name' => 'Rig', 'ownership' => 'owned', 'category' => 'drilling_rig', 'current_hour_meter' => '100', 'fuel_target_lph' => '10']);
        $service = app(EquipmentService::class);
        $service->recordMeter($e, '105', $u);
        $fuel = $service->recordFuel($e, '65', '100', '105', 'F-1', $u);
        $this->assertSame('13.0000', $fuel->liters_per_hour);
        $this->assertTrue($fuel->is_anomaly);
        $this->expectException(ValidationException::class);
        $service->recordMeter($e->refresh(), '99', $u);
    }
}
