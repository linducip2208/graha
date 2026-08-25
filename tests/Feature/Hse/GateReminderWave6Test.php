<?php

namespace Tests\Feature\Hse;

use App\Models\BoredPile;
use App\Models\CalibrationRecord;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Project;
use App\Models\ProjectZone;
use App\Models\User;
use App\Services\FieldOpsService;
use App\Services\ItpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GateReminderWave6Test extends TestCase
{
    use RefreshDatabase;

    public function test_itp_hold_gate_blocks_completion_only_when_enabled(): void
    {
        [$company, $owner] = $this->fixture();
        [$zone, $pile] = $this->pileFixture($company, $owner);
        $inspector = User::factory()->create();
        $itp = app(ItpService::class)->createPlan($this->projectFor($company, $owner), $pile, [
            'company_id' => $company->id,
            'title' => 'ITP Completion',
            'items' => [['stage' => 'Vertikalitas', 'method' => 'Visual', 'acceptance_criteria' => '<= 1%', 'checkpoint_type' => 'hold']],
        ], $owner);
        unset($zone);

        // Setting default OFF: gate tidak menahan.
        app(FieldOpsService::class)->completionGate($pile->refresh());
        $this->assertTrue(true);

        // Setting ON + hold point terbuka → ditahan.
        CompanySetting::create(['company_id' => $company->id, 'key' => 'require_itp_hold_points_passed', 'value' => '1']);
        try {
            app(FieldOpsService::class)->completionGate($pile->refresh());
            $this->fail('Hold point terbuka harus menahan completion bila setting aktif.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Hold point pass → gate lolos.
        $item = $itp->items()->where('checkpoint_type', 'hold')->first();
        app(ItpService::class)->recordInspection($item, today()->toDateString(), 'pass', '0.5%', null, $company->id, $inspector, $owner);
        app(FieldOpsService::class)->completionGate($pile->refresh());
        $this->assertTrue(true);
    }

    public function test_calibration_reminder_notifies_once_per_day(): void
    {
        [$company, $owner] = $this->fixture();
        $equipment = Equipment::create(['company_id' => $company->id, 'code' => 'EQ-N1', 'name' => 'Rig N', 'ownership' => 'owned', 'category' => 'drilling', 'current_hour_meter' => '0']);
        CalibrationRecord::create(['company_id' => $company->id, 'equipment_id' => $equipment->id, 'instrument_name' => 'Torque', 'calibrated_at' => now()->subYear()->toDateString(), 'next_due_at' => now()->subDays(3)->toDateString(), 'created_by' => $owner->id]);

        $this->artisan('qms:notify-calibration')->assertSuccessful();
        $first = DatabaseNotification::where('notifiable_id', $owner->id)->where('data->event', 'calibration_overdue')->count();
        $this->assertSame(1, $first, 'Overdue calibration memicu satu notifikasi.');

        $this->artisan('qms:notify-calibration')->assertSuccessful();
        $second = DatabaseNotification::where('notifiable_id', $owner->id)->where('data->event', 'calibration_overdue')->count();
        $this->assertSame(1, $second, 'Eksekusi kedua di hari sama tidak menduplikasi.');
    }

    private function fixture(): array
    {
        static $n = 0;
        $n++;
        $company = Company::create(['code' => 'GPW6'.$n.uniqid()[0], 'name' => "GP W6-{$n}"]);
        $owner = User::factory()->create();
        $owner->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);

        return [$company, $owner];
    }

    /** @return array [zone, pile] */
    private function pileFixture(Company $company, User $owner): array
    {
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C-W6', 'name' => 'Pelanggan']);
        $project = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P-W6', 'name' => 'Proyek W6', 'contract_value' => '100000000', 'status' => 'in_progress']);
        $zone = ProjectZone::create(['project_id' => $project->id, 'code' => 'Z-W6', 'name' => 'Zona']);
        $pile = BoredPile::create(['project_id' => $project->id, 'project_zone_id' => $zone->id, 'pile_number' => 'PX-1', 'diameter_mm' => '800', 'planned_depth_m' => '20.000', 'status' => 'planned', 'created_by' => $owner->id]);

        return [$zone, $pile];
    }

    private function projectFor(Company $company, User $owner): Project
    {
        return Project::where('company_id', $company->id)->firstOrFail();
    }
}
