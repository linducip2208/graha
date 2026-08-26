<?php

namespace App\Http\Controllers;

use App\Models\BoredPile;
use App\Models\BoredPileDrillingLayer;
use App\Models\ConcreteDelivery;
use App\Models\Nonconformity;
use App\Models\PileAcceptance;
use App\Models\PileGeometryReading;
use App\Models\PileReadinessCheck;
use App\Models\PileTest;
use App\Models\PileTremieLog;
use App\Models\Project;
use App\Models\SlurryTest;
use App\Services\AuditTrail;
use App\Services\FoundationForecastService;
use App\Services\FoundationLookaheadService;
use App\Services\FoundationProductivityService;
use App\Services\PileCostService;
use App\Services\PileRiskService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Foundation Control Tower (ADR-055): satu workspace KPI produksi, risiko,
 * peta/grid pile, dan papan harian. Semua angka dari agregasi data nyata.
 */
class ProjectFoundationController extends Controller
{
    public function __construct(
        private PileRiskService $risk,
        private AuditTrail $audit,
    ) {}

    public function show(Request $request, Project $project, CurrentCompany $current)
    {
        abort_unless($project->company_id === $current->id(), 404);
        $today = now()->startOfDay();

        // --- Cards: distribusi status ---
        $statusCounts = BoredPile::where('project_id', $project->id)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $acceptedCount = PileAcceptance::where('project_id', $project->id)->where('status', 'accepted')->count();
        $openNcrCount = Nonconformity::where('project_id', $project->id)->where('status', '!=', 'closed')->count();
        $total = (int) $statusCounts->sum();

        // --- KPI hari ini ---
        $metersToday = (float) BoredPileDrillingLayer::query()
            ->join('bored_pile_drillings', 'bored_pile_drillings.id', '=', 'bored_pile_drilling_layers.bored_pile_drilling_id')
            ->where('bored_pile_drillings.company_id', $project->company_id)
            ->whereIn('bored_pile_drillings.bored_pile_id', BoredPile::where('project_id', $project->id)->select('id'))
            ->whereDate('bored_pile_drillings.drilling_started_at', $today)
            ->max('depth_to_m') ?? 0.0;

        $concreteToday = (float) ConcreteDelivery::where('project_id', $project->id)
            ->where('status', 'approved')
            ->whereDate('arrived_at', $today)
            ->sum('accepted_volume_m3');
        $startedToday = (int) BoredPile::where('project_id', $project->id)
            ->whereHas('activities', fn ($q) => $q->whereDate('started_at', $today))->count();
        $completedToday = (int) BoredPile::where('project_id', $project->id)
            ->whereHas('activities', fn ($q) => $q->where('to_status', 'completed')->whereDate('started_at', $today))->count();

        // --- KPI kualitas & produktivitas ---
        $avgOverbreak = (float) BoredPile::where('project_id', $project->id)->whereNotNull('overbreak_percent')->avg('overbreak_percent');
        $testPassed = (int) PileTest::where('project_id', $project->id)->where('result_status', 'passed')->count();
        $testFailed = (int) PileTest::where('project_id', $project->id)->where('result_status', 'failed')->count();
        $testPassRate = ($testPassed + $testFailed) > 0 ? round(100 * $testPassed / ($testPassed + $testFailed), 1) : null;
        $testsPending = (int) PileTest::where('project_id', $project->id)->where('result_status', 'scheduled')->count();

        $cycleTimes = $this->cycleTimesByPile($project);
        $avgCycleHours = $cycleTimes->isNotEmpty() ? round($cycleTimes->avg(), 1) : null;

        $rigsUsed = BoredPile::where('project_id', $project->id)->whereNotNull('rig_equipment_id')->distinct('rig_equipment_id')->count('rig_equipment_id');
        $rigsActiveToday = (int) BoredPile::where('project_id', $project->id)
            ->whereDate('updated_at', $today)->distinct('rig_equipment_id')->whereNotNull('rig_equipment_id')->count('rig_equipment_id');

        // --- Risk per pile ---
        $piles = BoredPile::where('project_id', $project->id)
            ->with(['zone', 'acceptance'])
            ->orderBy('pile_number')
            ->limit(200)
            ->get();
        $risks = $piles->mapWithKeys(fn (BoredPile $pile) => [$pile->id => $this->risk->evaluate($pile)]);
        $rows = $piles->map(fn (BoredPile $pile) => [
            'pile' => $pile,
            'risk' => $risks[$pile->id],
            'status_label' => str_replace('_', ' ', $pile->status),
        ]);
        $riskCounts = ['healthy' => 0, 'watch' => 0, 'critical' => 0];
        foreach ($risks as $risk) {
            $riskCounts[$risk['level']]++;
        }

        // --- Advanced KPI (ADR-076/077): readiness, slurry, tremie, cost, forecast ---
        $latestChecks = PileReadinessCheck::whereIn('bored_pile_id', $piles->pluck('id'))
            ->whereIn('kind', [PileReadinessCheck::KIND_DRILL, PileReadinessCheck::KIND_CAST])
            ->orderByDesc('id')->get()->unique(fn ($c) => $c->bored_pile_id.'|'.$c->kind);
        $readyDrillIds = $latestChecks->where('kind', 'drill')->where('status', 'READY')->pluck('bored_pile_id');
        $readyCastIds = $latestChecks->where('kind', 'cast')->where('status', 'READY_TO_CAST')->pluck('bored_pile_id');
        $notAcceptedCount = (int) BoredPile::where('project_id', $project->id)
            ->where('status', 'completed')
            ->whereDoesntHave('acceptance', fn ($q) => $q->where('status', 'accepted'))->count();

        $criticalSlurry = SlurryTest::whereIn('bored_pile_id', $piles->pluck('id'))
            ->where(function ($q) {
                $q->where('status', 'rejected')->orWhere(function ($q2) {
                    $q2->whereIn('phase', ['before_casting'])->where('status', 'pending');
                });
            })->count();
        $tremieWarnings = (int) PileTremieLog::whereIn('bored_pile_id', $piles->pluck('id'))
            ->whereIn('flag', ['warning', 'out_of_range'])->count();
        $geometryWarningIds = PileGeometryReading::whereIn('bored_pile_id', $piles->pluck('id'))
            ->selectRaw('bored_pile_id, MAX(verticality_percent) as max_vert')->groupBy('bored_pile_id')
            ->havingRaw('max_vert > 2')->pluck('bored_pile_id');

        $interruptionPiles = $piles->filter(fn ($p) => collect($risks[$p->id]['reasons'])->contains('code', 'concrete_interruption'))->pluck('id');
        $costSummary = app(PileCostService::class)->projectSummary($project);
        $prod7 = app(FoundationProductivityService::class)->projectMetrics($project, now()->subDays(7)->startOfDay(), now());
        $forecast = app(FoundationForecastService::class)->forecast($project);
        $lookahead3 = app(FoundationLookaheadService::class)->build($project, 3);
        $lookahead7 = app(FoundationLookaheadService::class)->build($project, 7);

        // --- Filter klik-through dari KPI ---
        $filter = $request->query('filter');
        $filteredIds = match ($filter) {
            'ready_drill' => $readyDrillIds,
            'ready_cast' => $readyCastIds,
            'accepted' => $piles->filter(fn ($p) => $p->acceptance?->status === 'accepted')->pluck('id'),
            'not_accepted' => $piles->filter(fn ($p) => $p->status === 'completed' && $p->acceptance?->status !== 'accepted')->pluck('id'),
            'critical_risk' => $piles->filter(fn ($p) => $risks[$p->id]['level'] === 'critical')->pluck('id'),
            'slurry' => SlurryTest::whereIn('bored_pile_id', $piles->pluck('id'))->where('status', 'rejected')->distinct()->pluck('bored_pile_id'),
            'tremie' => PileTremieLog::whereIn('bored_pile_id', $piles->pluck('id'))->whereIn('flag', ['warning', 'out_of_range'])->distinct()->pluck('bored_pile_id'),
            'interruption' => $interruptionPiles,
            'geometry' => $geometryWarningIds,
            default => null,
        };
        $visibleRows = $filteredIds === null ? $rows : $rows->filter(fn ($row) => $filteredIds->contains($row['pile']->id))->values();

        // --- Peta / grid fallback ---
        $geoPiles = $piles->filter(fn ($p) => filled($p->latitude) && filled($p->longitude));
        $bounds = $this->geoBounds($geoPiles);
        $mapMode = $geoPiles->count() >= 2 ? 'plan' : 'grid';
        $bx = max(0.0001, ($bounds['maxLng'] ?? 0) - ($bounds['minLng'] ?? 0));
        $by = max(0.0001, ($bounds['maxLat'] ?? 0) - ($bounds['minLat'] ?? 0));
        $geoPoints = $geoPiles->map(fn (BoredPile $p) => [
            'pile' => $p,
            'level' => $risks[$p->id]['level'],
            'left' => round(6 + 88 * (($p->longitude - $bounds['minLng']) / $bx), 2),
            'top' => round(94 - 88 * (($p->latitude - $bounds['minLat']) / $by), 2),
        ])->values();

        $zoneGroups = $piles->groupBy(fn ($p) => $p->zone?->name ?? '-')
            ->map(fn ($group) => $group->map(fn (BoredPile $p) => [
                'pile' => $p,
                'level' => $risks[$p->id]['level'],
                'status_short' => strtoupper(substr((string) $p->status, 0, 4)),
            ]))->values();

        $this->audit->record($project->company_id, $request->user()->id, 'foundation_control_viewed', $project);

        return view('projects.foundation-control', [
            'project' => $project,
            'statusCounts' => $statusCounts,
            'total' => $total,
            'acceptedCount' => $acceptedCount,
            'openNcrCount' => $openNcrCount,
            'kpi' => [
                'started_today' => $startedToday,
                'completed_today' => $completedToday,
                'meters_today' => round($metersToday, 2),
                'concrete_today' => round($concreteToday, 2),
                'avg_overbreak' => round($avgOverbreak, 2),
                'test_pass_rate' => $testPassRate,
                'tests_pending' => $testsPending,
                'rigs_active' => $rigsActiveToday,
                'rigs_total' => $rigsUsed,
                'avg_cycle_hours' => $avgCycleHours,
            ],
            'advanced' => [
                'ready_drill' => $readyDrillIds->count(),
                'ready_cast' => $readyCastIds->count(),
                'not_accepted' => $notAcceptedCount,
                'critical_slurry' => $criticalSlurry,
                'tremie_warnings' => $tremieWarnings,
                'interruptions' => $interruptionPiles->count(),
                'geometry_warnings' => $geometryWarningIds->count(),
                'cost_total' => $costSummary['total_cost'],
                'cost_rework' => $costSummary['rework_cost'],
                'prod7_meters_per_day' => $prod7['meters_per_day'],
                'prod7_piles_completed' => $prod7['piles_completed'],
                'forecast' => $forecast,
            ],
            'lookahead3' => $lookahead3,
            'lookahead7' => $lookahead7,
            'filter' => $filter,
            'rows' => $visibleRows,
            'riskCounts' => $riskCounts,
            'mapMode' => $mapMode,
            'geoPoints' => $geoPoints,
            'zoneGroups' => $zoneGroups,
        ]);
    }

    /** Total cycle time per pile (jam): aktivitas pertama → terakhir yang selesai. */
    private function cycleTimesByPile(Project $project): Collection
    {
        return BoredPile::where('project_id', $project->id)
            ->with('activities')
            ->get()
            ->map(function (BoredPile $pile) {
                $first = $pile->activities->min('started_at');
                $lastFinished = $pile->activities->whereNotNull('finished_at')->max('finished_at');

                return ($first && $lastFinished) ? max(0, $first->diffInHours($lastFinished)) : null;
            })
            ->filter();
    }

    private function geoBounds(Collection $piles): array
    {
        if ($piles->isEmpty()) {
            return [];
        }

        return [
            'minLat' => (float) $piles->min('latitude'), 'maxLat' => (float) $piles->max('latitude'),
            'minLng' => (float) $piles->min('longitude'), 'maxLng' => (float) $piles->max('longitude'),
        ];
    }
}
