<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialStatementService
{
    public function generate(int $companyId, string $from, string $to): array
    {
        $accounts = Account::where('company_id', $companyId)->orderBy('code')->get();
        $movements = DB::table('journal_entries as e')->join('journals as j', 'j.id', '=', 'e.journal_id')
            ->where('j.company_id', $companyId)->where('j.status', 'posted')->where('j.journal_date', '<=', $to)
            ->groupBy('e.account_id')->selectRaw('e.account_id, SUM(CASE WHEN j.journal_date < ? THEN e.debit - e.credit ELSE 0 END) opening_net, SUM(CASE WHEN j.journal_date >= ? THEN e.debit ELSE 0 END) period_debit, SUM(CASE WHEN j.journal_date >= ? THEN e.credit ELSE 0 END) period_credit, SUM(e.debit - e.credit) ending_net', [$from, $from, $from])->get()->keyBy('account_id');

        $rows = $accounts->map(function (Account $account) use ($movements): array {
            $movement = $movements->get($account->id);
            $opening = (string) ($movement->opening_net ?? '0');
            $debit = (string) ($movement->period_debit ?? '0');
            $credit = (string) ($movement->period_credit ?? '0');
            $ending = (string) ($movement->ending_net ?? '0');

            return ['account' => $account, 'opening_debit' => bccomp($opening, '0', 2) >= 0 ? $opening : '0', 'opening_credit' => bccomp($opening, '0', 2) < 0 ? bcmul($opening, '-1', 2) : '0', 'period_debit' => $debit, 'period_credit' => $credit, 'ending_debit' => bccomp($ending, '0', 2) >= 0 ? $ending : '0', 'ending_credit' => bccomp($ending, '0', 2) < 0 ? bcmul($ending, '-1', 2) : '0'];
        });
        $revenue = $this->sumNatural($rows, 'revenue', 'credit');
        $expense = $this->sumNatural($rows, 'expense', 'debit');
        $assets = $this->sumNatural($rows, 'asset', 'debit', true);
        $liabilities = $this->sumNatural($rows, 'liability', 'credit', true);
        $equity = $this->sumNatural($rows, 'equity', 'credit', true);
        $profit = bcsub($revenue, $expense, 2);
        $unclosedEarnings = bcsub($this->sumNatural($rows, 'revenue', 'credit', true), $this->sumNatural($rows, 'expense', 'debit', true), 2);

        return ['rows' => $rows, 'total_debit' => $this->sum($rows, 'ending_debit'), 'total_credit' => $this->sum($rows, 'ending_credit'), 'revenue' => $revenue, 'expense' => $expense, 'profit' => $profit, 'assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity, 'unclosed_earnings' => $unclosedEarnings, 'liabilities_equity_profit' => bcadd(bcadd($liabilities, $equity, 2), $unclosedEarnings, 2)];
    }

    private function sumNatural(Collection $rows, string $type, string $normal, bool $ending = false): string
    {
        return $rows->where(fn (array $row) => $row['account']->type === $type)->reduce(function (string $carry, array $row) use ($normal, $ending): string {
            $debit = $row[$ending ? 'ending_debit' : 'period_debit'];
            $credit = $row[$ending ? 'ending_credit' : 'period_credit'];

            return bcadd($carry, $normal === 'debit' ? bcsub($debit, $credit, 2) : bcsub($credit, $debit, 2), 2);
        }, '0');
    }

    private function sum(Collection $rows, string $column): string
    {
        return $rows->reduce(fn (string $carry, array $row) => bcadd($carry, $row[$column], 2), '0');
    }
}
