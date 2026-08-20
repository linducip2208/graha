<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\EquipmentMeterLog;
use App\Models\FuelUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EquipmentService
{
    public function recordMeter(Equipment $equipment, string $reading, User $actor): EquipmentMeterLog
    {
        return DB::transaction(function () use ($equipment, $reading, $actor) {
            $equipment = Equipment::lockForUpdate()->findOrFail($equipment->id);
            throw_if(bccomp($reading, (string) $equipment->current_hour_meter, 2) < 0, ValidationException::withMessages(['reading' => 'Hour meter tidak boleh mundur.']));
            $log = EquipmentMeterLog::create(['equipment_id' => $equipment->id, 'reading' => $reading, 'recorded_at' => now(), 'recorded_by' => $actor->id]);
            $equipment->update(['current_hour_meter' => $reading]);

            return $log;
        }, 3);
    }

    public function recordFuel(Equipment $equipment, string $liters, string $start, string $end, string $reference, User $actor, ?int $projectId = null): FuelUsage
    {
        return DB::transaction(function () use ($equipment, $liters, $start, $end, $reference, $actor, $projectId) {
            $hours = bcsub($end, $start, 2);
            throw_if(bccomp($liters, '0', 4) <= 0 || bccomp($hours, '0', 2) <= 0, ValidationException::withMessages(['fuel' => 'Liter dan selisih hour meter harus positif.']));
            $lph = bcdiv($liters, $hours, 4);
            $anomaly = $equipment->fuel_target_lph !== null && bccomp($lph, bcmul((string) $equipment->fuel_target_lph, '1.20', 4), 4) === 1;

            return FuelUsage::create(['company_id' => $equipment->company_id, 'equipment_id' => $equipment->id, 'project_id' => $projectId, 'liters' => $liters, 'start_meter' => $start, 'end_meter' => $end, 'liters_per_hour' => $lph, 'is_anomaly' => $anomaly, 'reference' => $reference, 'recorded_by' => $actor->id, 'used_at' => now()]);
        }, 3);
    }
}
