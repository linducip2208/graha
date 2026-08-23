<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Cash Flow Forecast (ADR-052): proyeksi arus kas masuk/keluar dari data
 * nyata yang sudah ada — outstanding AR (billing posted belum dibayar) dan
 * outstanding AP (invoice matched belum dibayar) — diagregasi per window
 * kumulatif 7/30/90 hari dari tanggal jatuh tempo. Tanpa asumsi tersembunyi;
 * payroll/obligation lain menunggu sumber data (lihat gap analysis).
 */
class CashFlowForecastService
{
    public function __construct(private ReceivablePayableAgingService $aging) {}

    public function forecast(int $companyId): array
    {
        $asOf = Carbon::today();
        $report = $this->aging->generate($companyId, $asOf);
        $windows = [7, 30, 90];

        $bucketize = function ($rows) use ($asOf, $windows): array {
            $out = array_fill_keys(array_map(fn ($w) => "d{$w}", $windows), '0');
            foreach ($rows as $row) {
                $days = max(0, (int) $row['due_date']->diffInDays($asOf, false));
                foreach ($windows as $w) {
                    if ($days <= $w) {
                        $out["d{$w}"] = bcadd($out["d{$w}"], $row['outstanding'], 2);
                    }
                }
            }

            return $out;
        };

        $inflow = $bucketize($report['receivables']);
        $outflow = $bucketize($report['payables']);
        $net = [];
        foreach ($windows as $w) {
            $net["d{$w}"] = bcsub($inflow["d{$w}"], $outflow["d{$w}"], 2);
        }

        return [
            'as_of' => $asOf->toDateString(),
            'windows' => $windows,
            'inflow' => $inflow,
            'outflow' => $outflow,
            'net' => $net,
            'ar_overdue' => (float) $report['receivables']->sum(fn ($r) => max(0, (int) $r['due_date']->diffInDays($asOf, false)) > 0 ? $r['outstanding'] : 0),
        ];
    }
}
