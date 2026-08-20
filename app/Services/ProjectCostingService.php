<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectCostForecast;
use App\Models\ProjectCostLedger;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProjectCostingService
{
    public function __construct(private AuditTrail $audit) {}

    public function forecast(Project $project, array $data, User $actor): ProjectCostForecast
    {
        return DB::transaction(function () use ($project, $data, $actor) {
            if ($existing = ProjectCostForecast::where('company_id', $project->company_id)->where('idempotency_key', $data['idempotency_key'])->first()) {
                return $existing;
            }
            $forecast = ProjectCostForecast::create([...$data, 'company_id' => $project->company_id, 'project_id' => $project->id, 'created_by' => $actor->id]);
            $this->audit->record($project->company_id, $actor->id, 'project.cost_forecast_created', $forecast);

            return $forecast;
        }, 3);
    }

    public function summary(Project $project): array
    {
        $actual = (string) ProjectCostLedger::where('project_id', $project->id)->where('cost_type', 'actual')->sum('amount');
        $committed = (string) PurchaseOrder::where('company_id', $project->company_id)->whereIn('status', ['approved', 'issued', 'partially_received', 'received'])->whereHas('purchaseRequest', fn ($q) => $q->where('project_id', $project->id))->sum('total');
        $forecast = ProjectCostForecast::where('project_id', $project->id)->where('status', 'active')->latest('forecast_date')->latest('id')->first();
        $remainingBudget = bcsub((string) ($project->estimated_cost ?? '0'), $actual, 2);
        $ctc = (string) ($forecast?->cost_to_complete ?? (bccomp($remainingBudget, '0', 2) === 1 ? $remainingBudget : '0'));
        $eac = bcadd($actual, $ctc, 2);
        $budget = (string) ($project->estimated_cost ?? '0');

        return ['actual' => $actual, 'committed' => $committed, 'cost_to_complete' => $ctc, 'eac' => $eac, 'budget' => $budget, 'variance' => bcsub($budget, $eac, 2), 'contract_value' => (string) ($project->contract_value ?? '0'), 'forecast' => $forecast];
    }
}
