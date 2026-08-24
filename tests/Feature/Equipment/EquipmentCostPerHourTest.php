<?php

namespace Tests\Feature\Equipment;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\Company;
use App\Models\Equipment;
use App\Models\EquipmentMeterLog;
use App\Models\FiscalPeriod;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\FuelUsage;
use App\Models\MaintenanceWorkOrder;
use App\Models\NumberSequence;
use App\Models\User;
use App\Services\EquipmentCostService;
use App\Services\EquipmentService;
use App\Services\FixedAssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentCostPerHourTest extends TestCase
{
    use RefreshDatabase;

    public function test_cost_per_hour_aggregates_fuel_maintenance_and_depreciation(): void
    {
        $c = Company::create(['code' => 'GP', 'name' => 'GP']);
        $u = User::factory()->create();
        $e = Equipment::create(['company_id' => $c->id, 'code' => 'RIG-1', 'name' => 'Rig', 'ownership' => 'owned', 'category' => 'drilling_rig', 'current_hour_meter' => '110']);

        // Jam operasi: meter 100 -> 110 dalam periode = 10 jam.
        EquipmentMeterLog::create(['equipment_id' => $e->id, 'reading' => '100.00', 'recorded_at' => now()->subDays(3), 'recorded_by' => $u->id]);
        EquipmentMeterLog::create(['equipment_id' => $e->id, 'reading' => '110.00', 'recorded_at' => now(), 'recorded_by' => $u->id]);

        // BBM berharga: 20 L x 15.000 = 300.000; tanpa harga dilaporkan terpisah.
        FuelUsage::create(['company_id' => $c->id, 'equipment_id' => $e->id, 'liters' => '20.0000', 'unit_cost' => '15000.0000', 'start_meter' => '100', 'end_meter' => '105', 'reference' => 'F-1', 'recorded_by' => $u->id, 'used_at' => now()->subDays(2)]);
        FuelUsage::create(['company_id' => $c->id, 'equipment_id' => $e->id, 'liters' => '5.0000', 'start_meter' => '105', 'end_meter' => '108', 'reference' => 'F-2', 'recorded_by' => $u->id, 'used_at' => now()->subDay()]);

        // Maintenance ditutup dengan biaya aktual.
        $wo = MaintenanceWorkOrder::create(['company_id' => $c->id, 'equipment_id' => $e->id, 'number' => 'WO-1', 'type' => 'corrective', 'status' => 'open', 'problem' => 'Kebocoran hidraulik', 'meter_reading' => '105', 'actual_cost' => '0', 'opened_by' => $u->id]);
        app(EquipmentService::class)->closeMaintenanceOrder($wo, ['actual_cost' => '500000'], $u);

        // Depresiasi aset tetap tertaut: 12 jt / 60 bulan = 200.000 per periode.
        $period = FiscalPeriod::create(['company_id' => $c->id, 'name' => 'AUG', 'starts_at' => now()->startOfMonth()->toDateString(), 'ends_at' => now()->endOfMonth()->toDateString(), 'status' => 'open']);
        NumberSequence::create(['company_id' => $c->id, 'document_type' => 'journal', 'prefix' => 'JV', 'padding' => 4, 'last_reset_year' => (int) now()->format('Y')]);
        $category = FixedAssetCategory::create(['company_id' => $c->id, 'code' => 'EQ', 'name' => 'Equipment', 'default_useful_life_months' => 60]);
        $asset = FixedAsset::create(['company_id' => $c->id, 'fixed_asset_category_id' => $category->id, 'code' => 'FA-RIG1', 'name' => 'Rig', 'acquisition_date' => now()->subMonths(2)->toDateString(), 'depreciation_start_date' => now()->subMonths(2)->toDateString(), 'acquisition_cost' => '12000000.00', 'residual_value' => '0.00', 'useful_life_months' => 60, 'created_by' => $u->id]);
        $expense = Account::create(['company_id' => $c->id, 'code' => 'DEP', 'name' => 'Dep expense', 'type' => 'expense', 'normal_balance' => 'debit']);
        $accumulated = Account::create(['company_id' => $c->id, 'code' => 'ACC', 'name' => 'Accum dep', 'type' => 'asset', 'normal_balance' => 'credit']);
        AccountingMapping::create(['company_id' => $c->id, 'event_type' => 'asset_depreciation', 'entry_side' => 'expense_debit', 'account_id' => $expense->id]);
        AccountingMapping::create(['company_id' => $c->id, 'event_type' => 'asset_depreciation', 'entry_side' => 'accumulated_credit', 'account_id' => $accumulated->id]);
        app(FixedAssetService::class)->depreciate($asset, $period, now()->toDateString(), 'dep-rig-'.now()->format('Ym'), $u);
        $e->update(['fixed_asset_id' => $asset->id]);

        $summary = app(EquipmentCostService::class)->summary($e->refresh(), now()->subDays(30)->startOfDay(), now()->endOfDay());

        $this->assertSame('10.00', $summary['hours']);
        $this->assertSame('300000.00', $summary['fuel_cost']);
        $this->assertSame('5.0000', $summary['unpriced_fuel_liters']);
        $this->assertSame('500000.00', $summary['maintenance_cost']);
        $this->assertSame('200000.00', $summary['depreciation_cost']);
        $this->assertSame('1000000.00', $summary['total_cost']);
        $this->assertSame('100000.00', $summary['cost_per_hour']);

        // Periode sebelum aset ada: total tetap konsisten dari sumber nyata.
        $empty = app(EquipmentCostService::class)->summary($e, now()->subMonths(3)->startOfMonth(), now()->subMonths(2)->startOfMonth());
        $this->assertSame('0.00', $empty['total_cost']);
    }

    public function test_hours_require_two_readings_and_unpriced_fuel_is_not_invented(): void
    {
        $c = Company::create(['code' => 'GP2', 'name' => 'GP2']);
        $u = User::factory()->create();
        $e = Equipment::create(['company_id' => $c->id, 'code' => 'EXC-1', 'name' => 'Excavator', 'ownership' => 'owned', 'category' => 'excavator']);
        EquipmentMeterLog::create(['equipment_id' => $e->id, 'reading' => '50.00', 'recorded_at' => now()->subDay(), 'recorded_by' => $u->id]);
        FuelUsage::create(['company_id' => $c->id, 'equipment_id' => $e->id, 'liters' => '12.0000', 'start_meter' => '50', 'end_meter' => '53', 'reference' => 'F-9', 'recorded_by' => $u->id, 'used_at' => now()]);
        $wo = MaintenanceWorkOrder::create(['company_id' => $c->id, 'equipment_id' => $e->id, 'number' => 'WO-2', 'type' => 'preventive', 'status' => 'open', 'problem' => 'Servis rutin', 'meter_reading' => '50', 'opened_by' => $u->id]);

        $service = app(EquipmentCostService::class);
        $summary = $service->summary($e, now()->subDays(7)->startOfDay(), now()->addDay()->endOfDay());

        $this->assertNull($summary['hours']);
        $this->assertNull($summary['cost_per_hour']);
        $this->assertSame('12.0000', $summary['unpriced_fuel_liters']);
        $this->assertSame('0.00', $summary['total_cost']);

        // WO ditutup tanpa biaya tetap tidak menciptakan biaya.
        app(EquipmentService::class)->closeMaintenanceOrder($wo, [], $u);
        $summary = $service->summary($e->refresh(), now()->subDays(7)->startOfDay(), now()->addDay()->endOfDay());
        $this->assertSame('0.00', $summary['maintenance_cost']);
        $this->assertSame('0.00', $summary['total_cost']);
    }
}
