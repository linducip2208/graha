<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\BoredPileActivity;
use App\Models\Equipment;
use App\Models\FuelUsage;
use App\Models\PileConcretePourInterval;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Productivity engine (ADR-077): metrik dari data nyata (drilling, casting,
 * aktivitas status) — TANPA KPI hardcoded. Agregasi anti-N+1 via query group.
 */
class FoundationProductivityService
{
    /** Metrik produktivitas proyek dalam rentang tanggal. */
    public function projectMetrics(Project $project, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $from ??= now()->subDays(30)->startOfDay();
        $to ??= now()->endOfDay();
        $pileIds = BoredPile::where('project_id', $project->id)->pluck('id');
        if ($pileIds->isEmpty()) {
            return ['days' => 0, 'meters' => 0.0, 'piles_completed' => 0, 'meters_per_day' => null,
                'piles_per_day' => null, 'avg_cycle_hours' => null, 'phase_hours' => [], 'by_rig' => [], 'by_diameter' => []];
        }

        // Meter bor per hari (lapisan terdalam per hari drilling).
        $dailyMeters = DB::table('bored_pile_drilling_layers')
            ->join('bored_pile_drillings', 'bored_pile_drillings.id', '=', 'bored_pile_drilling_layers.bored_pile_drilling_id')
            ->whereIn('bored_pile_drillings.bored_pile_id', $pileIds)
            ->whereBetween('bored_pile_drillings.drilling_started_at', [$from, $to])
            ->groupBy(DB::raw('DATE(bored_pile_drillings.drilling_started_at)'))
            ->selectRaw('DATE(bored_pile_drillings.drilling_started_at) as day, MAX(depth_to_m) as meters')
            ->get();

        $pileCompleted = BoredPile::whereIn('id', $pileIds)->where('status', 'completed')
            ->whereHas('activities', fn ($q) => $q->where('to_status', 'completed')->whereBetween('started_at', [$from, $to]))->count();

        // Durasi fase per pile — dihitung di PHP agar portable lintas driver DB.
        $drillingRows = DB::table('bored_pile_drillings')->whereIn('bored_pile_id', $pileIds)
            ->whereNotNull('drilling_finished_at')->whereBetween('drilling_started_at', [$from, $to])
            ->get(['drilling_started_at', 'drilling_finished_at']);
        $drillingMinutes = $drillingRows->sum(fn ($r) => max(0, strtotime($r->drilling_finished_at) - strtotime($r->drilling_started_at)) / 60);

        $castingBounds = PileConcretePourInterval::whereIn('bored_pile_id', $pileIds)
            ->whereBetween('recorded_at', [$from, $to])
            ->selectRaw('MIN(recorded_at) as first_cast, MAX(recorded_at) as last_cast')->first();
        $castingHours = ($castingBounds?->first_cast && $castingBounds?->last_cast)
            ? max(0, strtotime($castingBounds->last_cast) - strtotime($castingBounds->first_cast)) / 3600
            : 0;

        // Cycle time: aktivitas pertama → terlesai per pile (MIN/MAX dari DB berupa string → parse Carbon).
        $cycles = BoredPileActivity::whereIn('bored_pile_id', $pileIds)
            ->selectRaw('bored_pile_id, MIN(started_at) as first_start, MAX(finished_at) as last_finish')
            ->groupBy('bored_pile_id')->havingRaw('last_finish IS NOT NULL AND first_start IS NOT NULL')->get();
        $cycleHours = $cycles->map(function ($c) {
            if ($c->first_start === null || $c->last_finish === null) {
                return 0;
            }

            return max(0, Carbon::parse($c->first_start)->diffInHours(Carbon::parse($c->last_finish)));
        });

        $daysWorked = max(1, $dailyMeters->count());
        $totalMeters = round((float) $dailyMeters->sum('meters'), 2);
        $elapsedDays = max(1, (int) ceil(Carbon::parse($from)->diffInDays(Carbon::parse($to))));

        return [
            'days_worked' => $daysWorked,
            'meters' => $totalMeters,
            'piles_completed' => $pileCompleted,
            'meters_per_day' => round($totalMeters / $elapsedDays, 2),
            'piles_per_day' => round($pileCompleted / $elapsedDays, 3),
            'avg_cycle_hours' => $cycleHours->isNotEmpty() ? round($cycleHours->avg(), 1) : null,
            'phase_hours' => [
                'drilling' => round($drillingMinutes / 60, 1),
                'casting' => round($castingHours, 1),
            ],
            'by_rig' => $this->byRig($project, $from, $to),
            'by_diameter' => $this->byDiameter($project),
        ];
    }

    /** Breakdown jam & meter per rig — dari data drilling nyata (dihitung di PHP). */
    private function byRig(Project $project, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $rows = DB::table('bored_pile_drillings')
            ->join('bored_piles', 'bored_piles.id', '=', 'bored_pile_drillings.bored_pile_id')
            ->leftJoin('equipment', 'equipment.id', '=', 'bored_piles.rig_equipment_id')
            ->where('bored_piles.project_id', $project->id)
            ->whereNotNull('bored_pile_drillings.drilling_finished_at')
            ->whereBetween('bored_pile_drillings.drilling_started_at', [$from, $to])
            ->get(['equipment.code', 'equipment.id', 'bored_piles.id as pile_id',
                'bored_pile_drillings.drilling_started_at', 'bored_pile_drillings.drilling_finished_at']);

        return $rows->groupBy(fn ($r) => $r->code ?? '-')
            ->map(function ($group, $code) {
                $minutes = $group->sum(fn ($r) => max(0, strtotime($r->drilling_finished_at) - strtotime($r->drilling_started_at)) / 60);

                return (object) [
                    'rig_code' => $code,
                    'hours' => round($minutes / 60, 2),
                    'piles' => $group->unique('pile_id')->count(),
                ];
            })->sortByDesc('hours')->take(10)->values()->all();
    }

    private function byDiameter(Project $project): array
    {
        return DB::table('bored_piles')
            ->where('project_id', $project->id)
            ->whereNotNull('actual_depth_m')
            ->groupBy('diameter_mm')
            ->selectRaw('diameter_mm, COUNT(*) as piles, SUM(actual_depth_m) as meters')
            ->orderBy('diameter_mm')->get()->all();
    }

    /** Kinerja satu rig: utilisasi, meter/jam, BBM/liter per jam & per meter (data nyata). */
    public function rigPerformance(Equipment $rig, Project $project): array
    {
        $from = now()->subDays(30)->startOfDay();
        $to = now()->endOfDay();
        $summary = $this->equipmentCost->summary($rig, $from, $to);

        $rows = DB::table('bored_pile_drillings')
            ->join('bored_piles', 'bored_piles.id', '=', 'bored_pile_drillings.bored_pile_id')
            ->where('bored_piles.rig_equipment_id', $rig->id)
            ->where('bored_piles.project_id', $project->id)
            ->whereNotNull('bored_pile_drillings.drilling_finished_at')
            ->whereBetween('bored_pile_drillings.drilling_started_at', [$from, $to])
            ->selectRaw(
                'COALESCE(SUM(TIMESTAMPDIFF(MINUTE, bored_pile_drillings.drilling_started_at, bored_pile_drillings.drilling_finished_at)) / 60.0, 0) as productive_hours, '
                .'COALESCE(SUM(bored_piles.actual_depth_m), 0) as meters, COUNT(DISTINCT bored_piles.id) as piles'
            )->first();

        $productiveHours = (float) $rows->productive_hours;
        $periodHours = max(1, Carbon::parse($from)->diffInHours(Carbon::parse($to)));
        $fuelLiters = (float) FuelUsage::where('company_id', $rig->company_id)->where('equipment_id', $rig->id)
            ->whereBetween('used_at', [$from, $to])->sum('liters');

        return [
            'period_days' => 30,
            'productive_hours' => round($productiveHours, 2),
            'idle_hours' => round(max(0, $periodHours - $productiveHours), 2),
            'utilization_percent' => round(100 * $productiveHours / $periodHours, 1),
            'meters' => round((float) $rows->meters, 2),
            'meters_per_hour' => $productiveHours > 0 ? round((float) $rows->meters / $productiveHours, 2) : null,
            'piles_served' => (int) $rows->piles,
            'fuel_liters' => round($fuelLiters, 1),
            'fuel_liter_per_hour' => $productiveHours > 0 ? round($fuelLiters / $productiveHours, 2) : null,
            'fuel_liter_per_meter' => (float) $rows->meters > 0 ? round($fuelLiters / (float) $rows->meters, 2) : null,
            'cost_per_hour' => $summary['cost_per_hour'],
        ];
    }

    public function __construct(private EquipmentCostService $equipmentCost) {}
}
