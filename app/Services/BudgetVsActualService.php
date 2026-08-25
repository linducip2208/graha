<?php

namespace App\Services;

use App\Models\AccountBudget;
use Illuminate\Support\Facades\DB;

/**
 * Budget vs aktual per akun per periode fiskal (ADR-067).
 * Aktual = mutasi posted jurnal pada rentang periode, dinormalkan ke saldo natural
 * (expense/revenue memakai mutasi periode; asset/liability/equity memakai saldo akhir).
 */
class BudgetVsActualService
{
    public function generate(int $companyId): array
    {
        $budgets = AccountBudget::where('company_id', $companyId)->with(['account', 'period'])->orderBy('fiscal_period_id')->get();

        return $budgets->groupBy('fiscal_period_id')->map(function ($group) use ($companyId) {
            $period = $group->first()->period;
            $actuals = DB::table('journal_entries as e')
                ->join('journals as j', 'j.id', '=', 'e.journal_id')
                ->where('j.company_id', $companyId)
                ->where('j.status', 'posted')
                ->whereBetween('j.journal_date', [$period->starts_at->toDateString(), $period->ends_at->toDateString()])
                ->whereIn('e.account_id', $group->pluck('account_id'))
                ->groupBy('e.account_id')
                ->selectRaw('e.account_id, SUM(e.debit - e.credit) AS net')
                ->pluck('net', 'account_id');

            $rows = $group->map(function (AccountBudget $budget) use ($actuals): array {
                $natural = in_array($budget->account->type, ['revenue'], true) ? -1 : 1;
                $actual = bcmul((string) ($actuals[$budget->account_id] ?? '0'), (string) $natural, 2);
                $variance = bcsub((string) $budget->amount, $actual, 2);
                $usage = bccomp($budget->amount, '0', 2) === 1 ? bcdiv($actual, $budget->amount, 4) : null;
                // Untuk expense/asset: over = actual > budget. Untuk revenue: over = actual < budget.
                $over = $budget->account->type === 'revenue' ? bccomp($actual, $budget->amount, 2) === -1 : bccomp($actual, $budget->amount, 2) === 1;

                return ['budget' => $budget, 'actual' => $actual, 'variance' => $variance, 'usage' => $usage, 'over' => $over];
            });

            return ['period' => $period, 'rows' => $rows, 'total_budget' => $rows->sum(fn ($r) => (float) $r['budget']->amount), 'total_actual' => $rows->reduce(fn ($c, $r) => bcadd($c, $r['actual'], 2), '0')];
        })->values()->all();
    }
}
