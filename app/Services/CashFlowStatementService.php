<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Journal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Laporan arus kas metode langsung (ADR-055): setiap jurnal posted yang menggerakkan
 * akun kas dikelompokkan ke operating/investing/financing berdasarkan kategori arus kas
 * pada Chart of Accounts. Akun tanpa kategori eksplisit dianggap OPERATING — tandai akun
 * investasi (mis. pembelian aset tetap) sebagai investing dan pendanaan sebagai financing.
 * Jurnal dengan beberapa akun lawan dibagi proporsional nilai absolut barisnya (baris terakhir
 * menyerap sisa pembulatan agar jumlah kategori selalu persis sama dengan mutasi kas jurnal itu).
 */
class CashFlowStatementService
{
    public function generate(int $companyId, string $from, string $to): array
    {
        $accounts = Account::where('company_id', $companyId)->get()->keyBy('id');
        $bankLinked = BankAccount::where('company_id', $companyId)->pluck('account_id');
        $cashIds = $accounts->filter(fn (Account $a) => $a->is_cash || $bankLinked->contains($a->id))->keys();
        throw_if($cashIds->isEmpty(), ValidationException::withMessages(['cash' => 'Belum ada akun kas (tandai is_cash atau tautkan rekening bank ke GL).']));

        $journalIds = Journal::where('company_id', $companyId)->where('status', 'posted')
            ->whereBetween('journal_date', [$from, $to])
            ->whereIn('id', DB::table('journal_entries')->whereIn('account_id', $cashIds)->select('journal_id'))
            ->pluck('id');

        $buckets = ['operating_inflow' => '0.00', 'operating_outflow' => '0.00', 'investing_inflow' => '0.00', 'investing_outflow' => '0.00', 'financing_inflow' => '0.00', 'financing_outflow' => '0.00'];
        $entries = DB::table('journal_entries')->whereIn('journal_id', $journalIds)->orderBy('journal_id')->get(['journal_id', 'account_id', 'debit', 'credit']);

        foreach ($entries->groupBy('journal_id') as $lines) {
            $cashNet = '0';
            foreach ($lines as $line) {
                if ($cashIds->contains((int) $line->account_id)) {
                    $cashNet = bcadd($cashNet, bcsub((string) $line->debit, (string) $line->credit, 2), 2);
                }
            }
            if (bccomp($cashNet, '0', 2) === 0) {
                continue; // transfer antar kas atau jurnal tanpa arus kas bersih
            }
            $nonCash = $lines->reject(fn ($line) => $cashIds->contains((int) $line->account_id))->values();
            $totalAbs = $nonCash->reduce(fn (string $c, $l) => bcadd($c, $this->absLine($l), 2), '0');
            throw_if(bccomp($totalAbs, '0', 2) === 0, ValidationException::withMessages(['entries' => "Jurnal #{$lines->first()->journal_id} tidak memiliki baris lawan."]));

            $inflow = bccomp($cashNet, '0', 2) === 1;
            $absNet = bcmul($cashNet, $inflow ? '1' : '-1', 2);
            $remaining = $absNet;
            $last = $nonCash->count() - 1;
            foreach ($nonCash as $index => $line) {
                $category = $this->categoryOf($accounts[(int) $line->account_id] ?? null);
                if ($index === $last) {
                    $amount = $remaining;
                } else {
                    $amount = bcdiv(bcmul($absNet, $this->absLine($line), 4), $totalAbs, 2);
                    $remaining = bcsub($remaining, $amount, 2);
                }
                $buckets[$category.($inflow ? '_inflow' : '_outflow')] = bcadd($buckets[$category.($inflow ? '_inflow' : '_outflow')], $amount, 2);
            }
        }

        $operating = bcsub($buckets['operating_inflow'], $buckets['operating_outflow'], 2);
        $investing = bcsub($buckets['investing_inflow'], $buckets['investing_outflow'], 2);
        $financing = bcsub($buckets['financing_inflow'], $buckets['financing_outflow'], 2);
        $opening = (string) DB::table('journal_entries as e')->join('journals as j', 'j.id', '=', 'e.journal_id')
            ->where('j.company_id', $companyId)->where('j.status', 'posted')->where('j.journal_date', '<', $from)
            ->whereIn('e.account_id', $cashIds)
            ->selectRaw('COALESCE(SUM(e.debit - e.credit), 0) AS opening')->value('opening');
        $netChange = bcadd(bcadd($operating, $investing, 2), $financing, 2);

        return [
            'buckets' => $buckets,
            'operating_net' => $operating,
            'investing_net' => $investing,
            'financing_net' => $financing,
            'opening_cash' => $opening,
            'closing_cash' => bcadd($opening, $netChange, 2),
            'net_change' => $netChange,
            'cash_accounts' => $accounts->filter(fn (Account $a) => $cashIds->contains($a->id))->values(),
        ];
    }

    private function absLine(object $line): string
    {
        return bccomp((string) $line->debit, (string) $line->credit, 2) >= 0 ? (string) $line->debit : (string) $line->credit;
    }

    private function categoryOf(?Account $account): string
    {
        if ($account && in_array($account->cash_flow_category, ['operating', 'investing', 'financing'], true)) {
            return $account->cash_flow_category;
        }

        return 'operating';
    }
}
