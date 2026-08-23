<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectCostForecast;
use App\Models\ProjectCostLedger;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Collection;
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
        // ADR-053: Revised Budget = baseline approved; tanpa baseline fallback ke estimated_cost lama.
        $approved = BudgetBaselineService::activeApproved($project->id);
        $budget = $approved ? (string) $approved->total_budget : (string) ($project->estimated_cost ?? '0');
        $remainingBudget = bcsub($budget, $actual, 2);
        $ctc = (string) ($forecast?->cost_to_complete ?? (bccomp($remainingBudget, '0', 2) === 1 ? $remainingBudget : '0'));
        $eac = bcadd($actual, $ctc, 2);

        return ['actual' => $actual, 'committed' => $committed, 'cost_to_complete' => $ctc, 'eac' => $eac, 'budget' => $budget, 'variance' => bcsub($budget, $eac, 2), 'contract_value' => (string) ($project->contract_value ?? '0'), 'forecast' => $forecast, 'baseline_version' => $approved?->version];
    }

    /** Ringkasan biaya untuk banyak proyek sekaligus (anti N+1 di dashboard/portofolio). */
    public function summariesFor($projects): array
    {
        $projects = $projects instanceof Collection ? $projects : collect($projects);
        if ($projects->isEmpty()) {
            return [];
        }
        $ids = $projects->pluck('id');
        $companyId = $projects->first()->company_id;

        $actuals = ProjectCostLedger::whereIn('project_id', $ids)->where('cost_type', 'actual')
            ->groupBy('project_id')->selectRaw('project_id, SUM(amount) as total')->pluck('total', 'project_id');
        // Kolom di-qualify eksplisit: kedua tabel punya company_id & status — tanpa kualifikasi
        // MySQL menolak dengan "ambiguous column" (SQLite di test memaafkan).
        $committeds = PurchaseOrder::query()
            ->join('purchase_requests as pr', 'pr.id', '=', 'purchase_orders.purchase_request_id')
            ->where('purchase_orders.company_id', $companyId)
            ->whereIn('purchase_orders.status', ['approved', 'issued', 'partially_received', 'received'])
            ->whereIn('pr.project_id', $ids)
            ->groupBy('pr.project_id')
            ->selectRaw('pr.project_id as project_id, SUM(purchase_orders.total) as total')
            ->pluck('total', 'project_id');
        $forecasts = ProjectCostForecast::whereIn('project_id', $ids)->where('status', 'active')
            ->orderByDesc('forecast_date')->orderByDesc('id')->get()->unique('project_id');

        $summaries = [];
        foreach ($projects as $project) {
            $actual = (string) ($actuals[$project->id] ?? '0');
            $forecast = $forecasts->firstWhere('project_id', $project->id);
            $remainingBudget = bcsub((string) ($project->estimated_cost ?? '0'), $actual, 2);
            $ctc = (string) ($forecast?->cost_to_complete ?? (bccomp($remainingBudget, '0', 2) === 1 ? $remainingBudget : '0'));
            $budget = (string) ($project->estimated_cost ?? '0');
            $eac = bcadd($actual, $ctc, 2);
            $summaries[$project->id] = ['actual' => $actual, 'committed' => (string) ($committeds[$project->id] ?? '0'), 'cost_to_complete' => $ctc, 'eac' => $eac, 'budget' => $budget, 'variance' => bcsub($budget, $eac, 2), 'contract_value' => (string) ($project->contract_value ?? '0'), 'forecast' => $forecast];
        }

        return $summaries;
    }
}
