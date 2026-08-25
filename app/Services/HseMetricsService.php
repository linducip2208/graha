<?php

namespace App\Services;

use App\Models\HseExposureLog;
use App\Models\HseIncident;
use InvalidArgumentException;

/**
 * KPI keselamatan berbasis exposure nyata (ADR-058):
 * FR (Frequency Rate) = lost-time incidents × 1.000.000 / jam kerja.
 * SR (Severity Rate)  = hari hilang × 1.000.000 / jam kerja.
 * Sumber jam kerja = input bulanan hse_exposure_logs (payroll/timesheet), bukan perkiraan.
 */
class HseMetricsService
{
    public function summary(int $companyId, string $from, string $to): array
    {
        $manHours = bcadd((string) HseExposureLog::where('company_id', $companyId)
            ->whereBetween('period_month', [$from, $to])
            ->sum('man_hours'), '0', 2);
        if (bccomp($manHours, '0', 2) === 0) {
            throw new InvalidArgumentException('Belum ada data jam kerja (exposure log) pada periode ini.');
        }

        $incidents = HseIncident::where('company_id', $companyId)
            ->whereBetween('occurred_at', [$from.' 00:00:00', $to.' 23:59:59'])->get();
        $lostTime = $incidents->where('is_lost_time', true)->count();
        $lostDays = (int) $incidents->sum('lost_days');
        $recordable = $incidents->whereIn('severity', ['high', 'fatal'])->count();

        return [
            'man_hours' => $manHours,
            'total_incidents' => $incidents->count(),
            'lost_time_incidents' => $lostTime,
            'lost_days' => $lostDays,
            'fr' => $this->rate((string) $lostTime, $manHours),
            'sr' => $this->rate((string) $lostDays, $manHours),
            'trir' => $this->rate((string) $recordable, $manHours),
            'months' => HseExposureLog::where('company_id', $companyId)->whereBetween('period_month', [$from, $to])->orderBy('period_month')->get(),
        ];
    }

    private function rate(string $count, string $manHours): string
    {
        if (bccomp($manHours, '0', 2) === 0) {
            return '0';
        }

        return bcdiv(bcmul($count, '1000000'), $manHours, 2);
    }
}
