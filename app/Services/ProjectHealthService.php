<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\ProgressBilling;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Project Health (reuse engine dashboard — dipindah agar reusable).
 * health = green/yellow/red dari selisih progres fisik vs rencana,
 * threshold dari CompanySetting per company. TANPA kalkulasi baru.
 */
class ProjectHealthService
{
    /** @return Collection<int, array{project: Project, physical: float, planned: float, variance: float, eac: float, margin: ?float, health: string}> */
    public function portfolio(int $companyId): Collection
    {
        $yellow = max(1.0, (float) CompanySetting::val($companyId, 'project_health_yellow_percent'));
        $red = max($yellow + 0.1, (float) CompanySetting::val($companyId, 'project_health_red_percent'));

        $projects = Project::where('company_id', $companyId)->whereIn('status', ['active', 'in_progress'])->orderBy('code')->get();
        if ($projects->isEmpty()) {
            return collect();
        }

        // Batch aggregate: hindari N+1 pada portofolio (1 kueri per agregat, bukan per proyek).
        $billedByProject = ProgressBilling::whereIn('project_id', $projects->pluck('id'))->where('status', 'posted')
            ->groupBy('project_id')->selectRaw('project_id, SUM(gross_amount) as total')->pluck('total', 'project_id');
        $summaries = app(ProjectCostingService::class)->summariesFor($projects);

        return $projects->map(function (Project $project) use ($summaries, $billedByProject, $yellow, $red) {
            $summary = $summaries[$project->id];
            $contract = (float) ($project->contract_value ?: 0);
            $physical = 0.0;
            if ($contract > 0 && $project->planned_start && $project->planned_end) {
                $totalDays = max(1, $project->planned_start->diffInDays($project->planned_end));
                $elapsedDays = min($totalDays, max(0, $project->planned_start->diffInDays(now())));
                $billed = (float) ($billedByProject[$project->id] ?? 0);
                $physical = round(min(100.0, $billed * 100 / $contract), 1);
                $planned = round($elapsedDays * 100 / $totalDays, 1);
            } else {
                $planned = 100.0;
            }
            $variancePct = $physical - $planned;
            $health = abs($variancePct) >= $red ? 'red' : (abs($variancePct) >= $yellow ? 'yellow' : 'green');
            $margin = bccomp((string) $contract, '0', 2) === 1
                ? round((float) bcdiv(bcmul(bcsub((string) $contract, $summary['eac'], 2), '100', 4), (string) $contract, 4), 1)
                : null;

            return ['project' => $project, 'physical' => $physical, 'planned' => $planned, 'variance' => $variancePct, 'eac' => (float) $summary['eac'], 'margin' => $margin, 'health' => $health];
        });
    }

    /** Map [projectId => row] untuk lookup cepat di view. */
    public function map(int $companyId): Collection
    {
        return $this->portfolio($companyId)->keyBy(fn (array $row) => $row['project']->id);
    }
}
