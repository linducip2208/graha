<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Equipment;
use App\Models\EquipmentMeterLog;
use App\Models\FuelTank;
use App\Models\FuelUsage;
use App\Models\MaintenanceWorkOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EquipmentService
{
    public function __construct(private AuditTrail $audit, private FuelTankService $fuelTanks) {}

    /** Tutup maintenance work order + daftarkan ke registry dokumen (idempotent). */
    public function closeMaintenanceOrder(MaintenanceWorkOrder $wo, array $data, User $actor): MaintenanceWorkOrder
    {
        return DB::transaction(function () use ($wo, $data, $actor) {
            $wo = MaintenanceWorkOrder::lockForUpdate()->findOrFail($wo->id);
            throw_unless($wo->status === 'open', ValidationException::withMessages(['status' => 'Work order sudah ditutup.']));
            if (isset($data['actual_cost']) && $data['actual_cost'] !== '' && $data['actual_cost'] !== null) {
                $wo->actual_cost = $data['actual_cost'];
            }
            $wo->status = 'closed';
            $wo->closed_at = now();
            $wo->save();
            Document::firstOrCreate(
                ['company_id' => $wo->company_id, 'document_type' => 'maintenance_work_order', 'number' => $wo->number],
                ['title' => 'MWO '.$wo->number.' — '.$wo->equipment?->name, 'owner_id' => $actor->id, 'workflow_status' => 'approved', 'signature_status' => 'unsigned']
            );
            $this->audit->record($wo->company_id, $actor->id, 'equipment.mwo_closed', $wo);

            return $wo->refresh();
        }, 3);
    }

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

    public function recordFuel(Equipment $equipment, string $liters, string $start, string $end, string $reference, User $actor, ?int $projectId = null, ?int $fuelTankId = null): FuelUsage
    {
        return DB::transaction(function () use ($equipment, $liters, $start, $end, $reference, $actor, $projectId, $fuelTankId) {
            $hours = bcsub($end, $start, 2);
            throw_if(bccomp($liters, '0', 4) <= 0 || bccomp($hours, '0', 2) <= 0, ValidationException::withMessages(['fuel' => 'Liter dan selisih hour meter harus positif.']));
            $lph = bcdiv($liters, $hours, 4);
            $anomaly = $equipment->fuel_target_lph !== null && bccomp($lph, bcmul((string) $equipment->fuel_target_lph, '1.20', 4), 4) === 1;

            // Integrasi tangki BBM (ADR-044): pemakaian equipment otomatis mengurangi saldo tangki
            // sebagai issue_to_equipment ter-audit; saldo tidak boleh negatif.
            if ($fuelTankId !== null) {
                $tank = FuelTank::where('company_id', $equipment->company_id)->where('is_active', true)->lockForUpdate()->findOrFail($fuelTankId);
                throw_unless(bccomp($this->fuelTanks->balance($tank), $liters, 2) >= 0, ValidationException::withMessages(['fuel_tank_id' => 'Saldo tangki BBM tidak mencukupi untuk '.$liters.' L.']));
                $this->fuelTanks->record($tank, [
                    'type' => 'issue_to_equipment',
                    'occurred_at' => now(),
                    'reference' => $reference,
                    'liters' => $liters,
                    'notes' => "Pemakaian {$equipment->code} · {$lph} LPH",
                    'idempotency_key' => 'fuel-usage:'.$equipment->id.':'.$reference,
                    'project_id' => $projectId,
                    'equipment_id' => $equipment->id,
                ], $actor);
            }

            return FuelUsage::create(['company_id' => $equipment->company_id, 'equipment_id' => $equipment->id, 'project_id' => $projectId, 'liters' => $liters, 'start_meter' => $start, 'end_meter' => $end, 'liters_per_hour' => $lph, 'is_anomaly' => $anomaly, 'reference' => $reference, 'recorded_by' => $actor->id, 'used_at' => now()]);
        }, 3);
    }
}
