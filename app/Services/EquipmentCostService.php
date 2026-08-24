<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\FuelUsage;
use App\Models\MaintenanceWorkOrder;
use Illuminate\Support\Facades\DB;

/**
 * Biaya equipment per jam dihitung saat baca dari sumber data nyata (ADR-073):
 * BBM (liter x harga per liter saat pencatatan), maintenance (biaya aktual WO
 * ditutup dalam periode), dan depresiasi aset tetap yang tertaut ke equipment.
 * Jam operasi = selisih reading hour meter pertama -> terakhir dalam periode.
 * Komponen tanpa data tidak dikarang; cost/jam hanya bila jam terhitung > 0.
 */
class EquipmentCostService
{
    /**
     * @return array{hours:?string,fuel_cost:string,unpriced_fuel_liters:string,maintenance_cost:string,depreciation_cost:string,total_cost:string,cost_per_hour:?string}
     */
    public function summary(Equipment $equipment, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $parts = $this->summariesFor($equipment->company_id, $from, $to, [$equipment->id]);

        return $parts[$equipment->id] ?? $this->empty();
    }

    /** Agregasi anti-N+1 untuk seluruh equipment satu company, dikunci by id. */
    public function summariesFor(int $companyId, \DateTimeInterface $from, \DateTimeInterface $to, ?array $onlyIds = null): array
    {
        $equipmentIds = Equipment::where('company_id', $companyId)
            ->when($onlyIds !== null, fn ($q) => $q->whereKey($onlyIds))
            ->pluck('id');
        if ($equipmentIds->isEmpty()) {
            return [];
        }
        $result = [];
        foreach ($equipmentIds as $id) {
            $result[$id] = $this->empty();
        }

        $meterRanges = DB::table('equipment_meter_logs')
            ->whereIn('equipment_id', $equipmentIds)
            ->whereBetween('recorded_at', [$from, $to])
            ->groupBy('equipment_id')
            ->selectRaw('equipment_id, MIN(reading) as first_reading, MAX(reading) as last_reading')
            ->get();
        foreach ($meterRanges as $range) {
            if (bccomp((string) $range->last_reading, (string) $range->first_reading, 2) > 0) {
                $result[$range->equipment_id]['hours'] = bcsub((string) $range->last_reading, (string) $range->first_reading, 2);
            }
        }

        $fuelRows = FuelUsage::where('company_id', $companyId)
            ->whereIn('equipment_id', $equipmentIds)
            ->whereBetween('used_at', [$from, $to])
            ->get(['equipment_id', 'liters', 'unit_cost']);
        foreach ($fuelRows->filter(fn ($row) => $row->unit_cost !== null) as $row) {
            $target = &$result[$row->equipment_id];
            $target['fuel_cost'] = bcadd($target['fuel_cost'], bcmul((string) $row->liters, (string) $row->unit_cost, 4), 2);
        }
        foreach ($fuelRows->filter(fn ($row) => $row->unit_cost === null) as $row) {
            $result[$row->equipment_id]['unpriced_fuel_liters'] = bcadd($result[$row->equipment_id]['unpriced_fuel_liters'], (string) $row->liters, 4);
        }

        $maintenance = MaintenanceWorkOrder::where('company_id', $companyId)
            ->whereIn('equipment_id', $equipmentIds)
            ->where('status', 'closed')
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$from, $to])
            ->groupBy('equipment_id')
            ->selectRaw('equipment_id, SUM(actual_cost) as total')
            ->pluck('total', 'equipment_id');
        foreach ($maintenance as $equipmentId => $total) {
            $result[$equipmentId]['maintenance_cost'] = bcadd($result[$equipmentId]['maintenance_cost'], (string) $total, 2);
        }

        $assetLinks = Equipment::whereIn('id', $equipmentIds)->whereNotNull('fixed_asset_id')->pluck('fixed_asset_id', 'id');
        if ($assetLinks->isNotEmpty()) {
            $depreciations = DB::table('asset_depreciations')
                ->whereIn('fixed_asset_id', $assetLinks->unique()->values())
                ->whereBetween('depreciation_date', [$from->format('Y-m-d 00:00:00'), $to->format('Y-m-d 23:59:59')])
                ->groupBy('fixed_asset_id')
                ->selectRaw('fixed_asset_id, SUM(amount) as total')
                ->pluck('total', 'fixed_asset_id');
            foreach ($assetLinks as $equipmentId => $assetId) {
                if (isset($depreciations[$assetId])) {
                    $result[$equipmentId]['depreciation_cost'] = bcadd($result[$equipmentId]['depreciation_cost'], (string) $depreciations[$assetId], 2);
                }
            }
        }

        foreach ($result as &$summary) {
            $summary['total_cost'] = bcadd(bcadd($summary['fuel_cost'], $summary['maintenance_cost'], 2), $summary['depreciation_cost'], 2);
            if ($summary['hours'] !== null && bccomp($summary['hours'], '0', 2) > 0) {
                $summary['cost_per_hour'] = bcdiv($summary['total_cost'], $summary['hours'], 2);
            }
        }

        return $result;
    }

    private function empty(): array
    {
        return ['hours' => null, 'fuel_cost' => '0.00', 'unpriced_fuel_liters' => '0.0000', 'maintenance_cost' => '0.00', 'depreciation_cost' => '0.00', 'total_cost' => '0.00', 'cost_per_hour' => null];
    }
}
