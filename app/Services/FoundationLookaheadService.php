<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\ConstraintLog;
use App\Models\PileReadinessCheck;
use App\Models\Project;
use Illuminate\Support\Carbon;

/**
 * Lookahead deterministik 3/7 hari (ADR-077): pile terencana + snapshot
 * readiness terakhir + constraint aktif. TANPA penjadwalan AI — rencana dari
 * planned_date / urutan pile; perencana tetap manusia.
 */
class FoundationLookaheadService
{
    public function __construct(private PileReadinessService $readiness) {}

    /** @return array<int, array<string, mixed>> baris lookahead untuk window hari. */
    public function build(Project $project, int $days = 3): array
    {
        $windowEnd = now()->addDays($days)->endOfDay();

        return BoredPile::where('project_id', $project->id)
            ->whereIn('status', ['planned', 'hold', 'setting_out'])
            ->where(fn ($q) => $q->whereNull('planned_date')->orWhereBetween('planned_date', [now()->startOfDay(), $windowEnd]))
            ->with(['zone', 'rig'])
            ->orderByRaw('COALESCE(planned_date, ?) ASC', [now()->toDateString()])
            ->orderBy('pile_number')
            ->limit(60)
            ->get()
            ->map(function (BoredPile $pile) {
                $latest = PileReadinessCheck::where('bored_pile_id', $pile->id)
                    ->where('kind', PileReadinessCheck::KIND_DRILL)->latest('id')->first();
                $constraints = ConstraintLog::where('project_id', $pile->project_id)
                    ->where('status', '!=', 'resolved')
                    ->where(fn ($q) => $q->whereNull('bored_pile_id')->orWhere('bored_pile_id', $pile->id))
                    ->count();

                return [
                    'pile' => $pile,
                    'planned_date' => $pile->planned_date ? Carbon::parse($pile->planned_date) : null,
                    'zone' => $pile->zone?->name,
                    'rig' => $pile->rig?->code ?? $pile->operator_name,
                    'readiness_status' => $latest?->status ?? 'NOT_CHECKED',
                    'blockers' => count($latest?->blockers ?? []),
                    'active_constraints' => $constraints,
                ];
            })->all();
    }
}
