<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\ConcreteDelivery;
use App\Models\Project;
use App\Models\PurchaseOrderItem;
use App\Models\ReinforcementCage;
use App\Models\StockMovement;

/**
 * Per-pile cost intelligence (ADR-076): SEMUA angka dari transaksi nyata —
 * TANPA estimasi karangan. Komponen tanpa data transaksi dilaporkan null /
 * untraced, bukan dikarang. Drill-down: kategori → sumber transaksi.
 *
 * Rework attribution (deterministik):
 * - extra concrete: volume di atas teoretis pada pile overbreak → rework.
 * - redrilling: drilling record kedua dst. pada pile sama → jam × rate rig.
 */
class PileCostService
{
    public function __construct(private EquipmentCostService $equipmentCost) {}

    /** @return array<string, mixed> struktur biaya per pile (IDR). */
    public function pileCost(BoredPile $pile): array
    {
        $companyId = $pile->project->company_id;

        // --- Concrete: DO approved × harga satuan PO tertaut ---
        $deliveries = ConcreteDelivery::where('bored_pile_id', $pile->id)->where('status', 'approved')->orderBy('arrived_at')->get();
        $poPrices = $this->poUnitPrices($deliveries->pluck('purchase_order_id')->filter()->unique()->all());
        $concreteCost = '0';
        $reworkConcrete = '0';
        $untracedVolume = '0';
        $theoretical = (string) ($pile->theoretical_concrete_m3 ?? 0);
        $acceptedSoFar = '0';
        foreach ($deliveries as $delivery) {
            $price = $poPrices[$delivery->purchase_order_id] ?? null;
            $accepted = (string) $delivery->accepted_volume_m3;
            if ($price === null) {
                $untracedVolume = bcadd($untracedVolume, $accepted, 4);

                continue;
            }
            // Porsi normal dibatasi sisa volume teoretis; sisanya = extra (overbreak).
            $remainingTheoretical = bccomp($theoretical, '0', 4) === 1 ? self::max0(bcsub($theoretical, $acceptedSoFar, 4)) : $accepted;
            $normalPart = bccomp($accepted, $remainingTheoretical, 4) === 1 ? $remainingTheoretical : $accepted;
            $extraPart = bcsub($accepted, $normalPart, 4);
            $concreteCost = bcadd($concreteCost, bcmul($normalPart, $price, 2), 2);
            if ((bool) $pile->overbreak_exceeded) {
                $reworkConcrete = bcadd($reworkConcrete, bcmul($extraPart, $price, 2), 2);
            }
            $acceptedSoFar = bcadd($acceptedSoFar, $accepted, 4);
        }

        // --- Steel cage: konsumsi material cage terkirim ke pile (stock movement nyata) ---
        $cageIds = ReinforcementCage::where('company_id', $companyId)
            ->whereNotNull('bored_pile_id')->where('bored_pile_id', $pile->id)->pluck('id');
        $steelCost = '0';
        foreach ($cageIds as $cageId) {
            $steelCost = bcadd($steelCost, (string) StockMovement::where('company_id', $companyId)
                ->whereIn('movement_type', ['issue', 'return_in'])
                ->where('reference_type', 'reinforcement_cage')
                ->where('reference_id', (string) $cageId)
                ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'issue' THEN 1 ELSE -1 END * unit_cost * quantity), 0) as total")
                ->value('total'), 2);
        }

        // --- Material issue langsung ke pile (stock_movements.bored_pile_id, issue minus return) ---
        $materialIssueCost = (string) round((float) StockMovement::where('company_id', $companyId)
            ->where('bored_pile_id', $pile->id)
            ->whereIn('movement_type', ['issue', 'return_in'])
            ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'issue' THEN 1 ELSE -1 END * unit_cost * quantity), 0) as total")
            ->value('total'), 2);

        // --- Equipment rig: jam drilling nyata × cost/jam aktual periode proyek ---
        $rigHours = '0';
        $redrillHours = '0';
        $drillings = $pile->drillings()->whereNotNull('drilling_finished_at')->orderBy('drilling_started_at')->get();
        foreach ($drillings as $index => $drilling) {
            $hours = max(0, (float) $drilling->drilling_started_at?->diffInMinutes($drilling->drilling_finished_at) ?? 0) / 60;
            $rigHours = bcadd($rigHours, number_format($hours, 3, '.', ''), 3);
            if ($index > 0) {
                $redrillHours = bcadd($redrillHours, number_format($hours, 3, '.', ''), 3); // record kedua+ = redrill
            }
        }
        [$equipmentCost, $fuelCost, $ratePerHour] = ['0', '0', null];
        if ($pile->rig_equipment_id !== null && (float) $rigHours > 0) {
            $summary = $this->equipmentCost->summary($pile->rig, now()->subYear(), now());
            if ($summary['cost_per_hour'] !== null) {
                $ratePerHour = $summary['cost_per_hour'];
                $totalEquipment = bcmul($rigHours, $summary['cost_per_hour'], 2);
                $equipmentCost = bcadd($equipmentCost, $totalEquipment, 2);
                // Proporsi komponen BBM dari total biaya equipment (data nyata yang sama).
                if (bccomp($summary['fuel_cost'], '0', 2) === 1 && bccomp($summary['total_cost'], '0', 2) === 1) {
                    $share = bcdiv($summary['fuel_cost'], $summary['total_cost'], 6);
                    $fuelCost = bcmul($totalEquipment, $share, 2);
                }
            }
        }

        // --- Testing: nominal invoice aktual (kolom opsional) ---
        $testingCost = (string) $pile->tests()->sum('cost_amount');

        // --- Redrill equipment cost masuk rework ---
        $reworkEquipment = $ratePerHour !== null ? bcmul($redrillHours, $ratePerHour, 2) : '0';

        $reworkCost = bcadd($reworkConcrete, $reworkEquipment, 2);
        // Total biaya mencakup extra concrete; normal_cost = total - rework (subset).
        $concreteTotal = bcadd($concreteCost, $reworkConcrete, 2);
        $actualCost = bcadd(bcadd(bcadd(bcadd(bcadd($concreteTotal, $steelCost, 2), $materialIssueCost, 2), $equipmentCost, 2), $testingCost, 2), '0', 2);

        $meters = max(0.001, (float) $pile->actual_depth_m);

        return [
            'budget_if_available' => $this->wbsBudget($pile),
            'actual_cost' => $actualCost,
            'variance' => null, // hanya bila budget tersedia
            'cost_per_meter' => bcmul(bcdiv($actualCost, number_format($meters, 3, '.', ''), 4), '1', 2),
            'concrete_cost' => $concreteTotal,
            'steel_cost' => $steelCost,
            'material_issue_cost' => $materialIssueCost,
            'equipment_cost' => $equipmentCost,
            'fuel_cost' => $fuelCost,
            'testing_cost' => $testingCost,
            'other_cost' => '0',
            'rework_cost' => $reworkCost,
            'normal_cost' => bcsub($actualCost, $reworkCost, 2),
            'rework_breakdown' => [
                'extra_concrete' => $reworkConcrete,
                'redrill_hours' => $redrillHours,
                'redrill_equipment' => $reworkEquipment,
            ],
            'untraced_concrete_volume_m3' => $untracedVolume,
            'rig_hours' => $rigHours,
            'rig_rate_per_hour' => $ratePerHour,
            'sources' => $this->sourceLinks($pile),
        ];
    }

    /** Harga satuan beton per PO dari item PO nyata (rata-rata baris). */
    private function poUnitPrices(array $poIds): array
    {
        if ($poIds === []) {
            return [];
        }

        return PurchaseOrderItem::whereIn('purchase_order_id', $poIds)
            ->groupBy('purchase_order_id')
            ->selectRaw('purchase_order_id, AVG(unit_price) as price')
            ->pluck('price', 'purchase_order_id')
            ->map(fn ($p) => (string) $p)
            ->all();
    }

    private function wbsBudget(BoredPile $pile): ?string
    {
        if ($pile->project_wbs_id === null) {
            return null;
        }
        $budget = DB::table('project_wbs')->where('id', $pile->project_wbs_id)->value('budget');

        return $budget !== null ? (string) $budget : null;
    }

    private function sourceLinks(BoredPile $pile): array
    {
        return [
            'concrete' => 'concrete_deliveries × purchase_order_items',
            'steel' => 'stock_movements (cage-material:{cage_id})',
            'material_issue' => 'material_request_lines + stock_movements',
            'equipment' => 'bored_pile_drillings × equipment cost/hour (BBM+maintenance+depresiasi)',
            'testing' => 'pile_tests.cost_amount (input manual invoice)',
        ];
    }

    private static function max0(string $v): string
    {
        return bccomp($v, '0', 4) === -1 ? '0' : $v;
    }

    /** Ringkasan agregat rework & variance untuk satu project (anti N+1). */
    public function projectSummary(Project $project): array
    {
        $piles = BoredPile::where('project_id', $project->id)->with('project')->get(['id', 'project_id']);
        $total = '0';
        $rework = '0';
        foreach ($piles as $pile) {
            $cost = $this->pileCost($pile);
            $total = bcadd($total, $cost['actual_cost'], 2);
            $rework = bcadd($rework, $cost['rework_cost'], 2);
        }

        return ['total_cost' => $total, 'rework_cost' => $rework, 'normal_cost' => bcsub($total, $rework, 2)];
    }
}
