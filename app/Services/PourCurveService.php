<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\PileConcretePourInterval;
use App\Models\PileGeometryReading;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pour curve & hole geometry (ADR-075): semua perhitungan deterministik dari
 * data interval/pembacaan nyata — TANPA interpolasi fiktif. Bila data interval
 * kosong, UI menampilkan "belum ada data" (bukan grafik karangan).
 */
class PourCurveService
{
    public function __construct(private AuditTrail $audit) {}

    public function recordInterval(BoredPile $pile, array $data, User $actor): PileConcretePourInterval
    {
        return DB::transaction(function () use ($pile, $data, $actor) {
            throw_if(bccomp((string) $data['incremental_volume_m3'], '0', 4) === -1, ValidationException::withMessages(['incremental_volume_m3' => 'Volume inkremental tidak boleh negatif.']));
            $sequence = ((int) PileConcretePourInterval::where('bored_pile_id', $pile->id)->max('sequence')) + 1;
            $cumulative = (string) PileConcretePourInterval::where('bored_pile_id', $pile->id)->sum('incremental_volume_m3');
            $interval = PileConcretePourInterval::create([
                ...$data,
                'company_id' => $pile->project->company_id,
                'bored_pile_id' => $pile->id,
                'sequence' => $sequence,
                'recorded_by' => $actor->id,
                // Kumulatif aktual = jumlah seluruh inkremental sampai titik ini.
                'cumulative_volume_m3' => bcadd($cumulative, (string) $data['incremental_volume_m3'], 4),
            ]);
            $this->audit->record($pile->project->company_id, $actor->id, 'field.pour_interval_recorded', $interval);

            return $interval;
        }, 3);
    }

    /**
     * Kurva pour: theoretical vs aktual kumulatif per kedalaman + variance %.
     *
     * @return array{points: array<int, array{depth: float|string, theoretical: float|string, actual: float|string|null, variance_percent: float|null, overconsumed: bool}>, total_actual: string, total_theoretical: float}
     */
    public function curve(BoredPile $pile): array
    {
        $radiusM = bcdiv((string) $pile->diameter_mm, '2000', 8);
        $areaM2 = bcmul(bcmul('3.14159265', bcmul($radiusM, $radiusM, 8), 8), '1', 8);
        $tolerance = max(1.0, (float) ($pile->project->overbreak_tolerance_percent ?? 8));

        $points = [];
        $runningActual = '0';
        foreach ($pile->pourIntervals()->orderBy('depth_or_level_m')->get() as $interval) {
            $theoretical = bcmul($areaM2, (string) $interval->depth_or_level_m, 4);
            $runningActual = bcadd($runningActual, (string) $interval->incremental_volume_m3, 4);
            $variance = bccomp($theoretical, '0', 4) === 1
                ? (float) bcmul(bcsub(bcdiv($runningActual, $theoretical, 6), '1', 6), '100', 3)
                : null;
            $points[] = [
                'depth' => (float) $interval->depth_or_level_m,
                'theoretical' => round((float) $theoretical, 3),
                'actual' => round((float) $runningActual, 3),
                'variance_percent' => $variance !== null ? round($variance, 2) : null,
                'overconsumed' => $variance !== null && $variance > $tolerance,
            ];
        }

        return [
            'points' => $points,
            'total_actual' => $runningActual,
            'total_theoretical' => round((float) bcmul($areaM2, (string) $pile->actual_depth_m, 4), 3),
        ];
    }

    public function importGeometryCsv(BoredPile $pile, string $csvContent, string $source, User $actor): int
    {
        throw_unless(in_array($source, PileGeometryReading::SOURCES, true), ValidationException::withMessages(['source' => 'Sumber pembacaan tidak dikenal.']));
        $rows = preg_split('/\r\n|\r|\n/', trim($csvContent));
        $count = 0;
        DB::transaction(function () use ($rows, $pile, $source, $actor, &$count) {
            foreach ($rows as $index => $row) {
                $cells = str_getcsv($row);
                if (count($cells) < 1 || trim((string) $cells[0]) === '') {
                    continue;
                }
                if ($index === 0 && ! is_numeric(str_replace(',', '.', trim((string) $cells[0])))) {
                    continue; // header
                }
                $num = fn (?string $v) => filled($v) ? str_replace(',', '.', trim($v)) : null;
                PileGeometryReading::create([
                    'company_id' => $pile->project->company_id,
                    'bored_pile_id' => $pile->id,
                    'depth_m' => $num($cells[0] ?? null),
                    'measured_diameter_mm' => $num($cells[1] ?? null),
                    'deviation_x_mm' => $num($cells[2] ?? null),
                    'deviation_y_mm' => $num($cells[3] ?? null),
                    'verticality_percent' => $num($cells[4] ?? null),
                    'recorded_at' => now(),
                    'source' => $source,
                    'notes' => 'CSV import',
                    'recorded_by' => $actor->id,
                ]);
                $count++;
            }
            if ($count === 0) {
                throw ValidationException::withMessages(['file' => 'Tidak ada baris data valid pada CSV.']);
            }
        });
        $this->audit->record($pile->project->company_id, $actor->id, 'field.geometry_imported', $pile);

        return $count;
    }
}
