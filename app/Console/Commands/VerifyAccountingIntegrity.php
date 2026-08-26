<?php

namespace App\Console\Commands;

use App\Models\Journal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyAccountingIntegrity extends Command
{
    protected $signature = 'accounting:verify {--company= : Batasi company ID}';

    protected $description = 'Read-only verifier untuk balance, period, company scope, dan posting metadata jurnal';

    public function handle(): int
    {
        $anomalies = [];
        Journal::query()
            ->when($this->option('company'), fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->chunkById(500, function ($journals) use (&$anomalies) {
                $ids = $journals->pluck('id');
                $totals = DB::table('journal_entries')
                    ->whereIn('journal_id', $ids)
                    ->selectRaw('journal_id, COALESCE(SUM(debit), 0) debit, COALESCE(SUM(credit), 0) credit, COUNT(*) line_count')
                    ->groupBy('journal_id')
                    ->get()
                    ->keyBy('journal_id');
                $periods = DB::table('fiscal_periods')->whereIn('id', $journals->pluck('fiscal_period_id'))->get()->keyBy('id');
                $crossCompanyEntries = DB::table('journal_entries as entries')
                    ->join('accounts', 'accounts.id', '=', 'entries.account_id')
                    ->join('journals', 'journals.id', '=', 'entries.journal_id')
                    ->whereIn('entries.journal_id', $ids)
                    ->whereColumn('accounts.company_id', '!=', 'journals.company_id')
                    ->pluck('entries.journal_id')
                    ->flip();

                foreach ($journals as $journal) {
                    $total = $totals->get($journal->id);
                    if (! $total || (int) $total->line_count < 2 || bccomp((string) $total->debit, '0', 2) !== 1 || bccomp((string) $total->debit, (string) $total->credit, 2) !== 0) {
                        $anomalies[] = [$journal->id, 'UNBALANCED_OR_EMPTY', $total?->debit ?? '0', $total?->credit ?? '0'];
                    }
                    $period = $periods->get($journal->fiscal_period_id);
                    if (! $period || (int) $period->company_id !== (int) $journal->company_id || $journal->journal_date->format('Y-m-d') < $period->starts_at || $journal->journal_date->format('Y-m-d') > $period->ends_at) {
                        $anomalies[] = [$journal->id, 'PERIOD_SCOPE_OR_DATE_MISMATCH', '-', '-'];
                    }
                    if ($journal->status === 'posted' && (! $journal->posted_by || ! $journal->posted_at)) {
                        $anomalies[] = [$journal->id, 'POSTED_WITHOUT_METADATA', '-', '-'];
                    }
                    if ($crossCompanyEntries->has($journal->id)) {
                        $anomalies[] = [$journal->id, 'CROSS_COMPANY_ACCOUNT', '-', '-'];
                    }
                }
            });

        $this->table(['Journal ID', 'Anomaly', 'Debit', 'Credit'], $anomalies);
        $this->line($anomalies === [] ? 'PASS - tidak ada anomali accounting.' : 'FAIL - '.count($anomalies).' anomali ditemukan; tidak ada auto-fix.');

        return $anomalies === [] ? self::SUCCESS : self::FAILURE;
    }
}
