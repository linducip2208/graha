<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\Project;

/**
 * Completion forecast deterministik (ADR-077) — TANPA AI: ekstrapolasi
 * produktivitas nyata 7/14 hari dengan label confidence berbasis kecukupan
 * data. Riwayat kosong → "insufficient history", bukan angka karangan.
 */
class FoundationForecastService
{
    public function __construct(private FoundationProductivityService $productivity) {}

    /** @return array{forecast_completion_date: string|null, method: string, remaining_piles: int, piles_per_day_7d: float|null, piles_per_day_14d: float|null, active_rigs: int|null, confidence: string} */
    public function forecast(Project $project): array
    {
        $remaining = (int) BoredPile::where('project_id', $project->id)->whereNotIn('status', ['completed', 'rejected'])->count();

        $m14 = $this->productivity->projectMetrics($project, now()->subDays(14)->startOfDay(), now());
        $m7 = $this->productivity->projectMetrics($project, now()->subDays(7)->startOfDay(), now());

        $rate14 = $m14['piles_per_day'];
        $rate7 = $m7['piles_per_day'];

        // Prioritas rate: rata-rata tertimbang 7d (2x) dan 14d bila keduanya ada.
        if ($rate7 > 0 && $rate14 > 0) {
            $rate = ($rate7 * 2 + $rate14) / 3;
            $method = 'weighted_avg_7d_14d';
        } elseif ($rate7 > 0) {
            $rate = $rate7;
            $method = 'last_7_days';
        } elseif ($rate14 > 0) {
            $rate = $rate14;
            $method = 'last_14_days';
        } else {
            return [
                'forecast_completion_date' => null,
                'method' => 'insufficient_history',
                'remaining_piles' => $remaining,
                'piles_per_day_7d' => $rate7 ?: null,
                'piles_per_day_14d' => $rate14 ?: null,
                'active_rigs' => $this->activeRigs($project),
                'confidence' => 'insufficient',
            ];
        }

        $daysNeeded = (int) ceil($remaining / max(0.001, $rate));
        // Aktif rig 0 → kapasitas jatuh; sederhana: forecast tetap linear (deterministik).
        $completion = now()->addDays($daysNeeded);

        $completedInWindow = max($m7['piles_completed'], $m14['piles_completed']);
        $confidence = $completedInWindow >= 10 ? 'high' : ($completedInWindow >= 3 ? 'medium' : 'low');

        return [
            'forecast_completion_date' => $completion->toDateString(),
            'method' => $method,
            'remaining_piles' => $remaining,
            'piles_per_day_7d' => $rate7 ?: null,
            'piles_per_day_14d' => $rate14 ?: null,
            'active_rigs' => $this->activeRigs($project),
            'confidence' => $confidence,
        ];
    }

    private function activeRigs(Project $project): int
    {
        return (int) BoredPile::where('project_id', $project->id)
            ->whereNotNull('rig_equipment_id')->distinct('rig_equipment_id')->count('rig_equipment_id');
    }
}
