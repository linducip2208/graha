<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\CompanySetting;

/**
 * Survey control (ADR-075): deviasi horizontal & elevasi dari koordinat
 * design vs aktual. Status PASS/WARNING/OUT_OF_TOLERANCE hanya indikator —
 * disposisi engineering tetap manusia.
 */
class PileSurveyService
{
    /** @return array{horizontal_deviation_m: float|null, elevation_deviation_m: float|null, cutoff_deviation_m: float|null, status: string, tolerance_m: float} */
    public function deviation(BoredPile $pile): array
    {
        $tolerance = max(0.001, (float) CompanySetting::val($pile->project->company_id, 'survey_tolerance_m'));

        $horizontal = null;
        if (filled($pile->design_easting) && filled($pile->actual_easting) && filled($pile->design_northing) && filled($pile->actual_northing)) {
            $dx = (float) bcsub((string) $pile->actual_easting, (string) $pile->design_easting, 4);
            $dy = (float) bcsub((string) $pile->actual_northing, (string) $pile->design_northing, 4);
            $horizontal = round(sqrt($dx * $dx + $dy * $dy), 3);
        }

        $elevation = null;
        if (filled($pile->design_top_elevation) && filled($pile->actual_top_elevation)) {
            $elevation = round(abs((float) bcsub((string) $pile->actual_top_elevation, (string) $pile->design_top_elevation, 3)), 3);
        }

        // Cut-off: gunakan pasangan design/actual bila ada; fallback ke cut_off_level tunggal.
        $cutoff = null;
        if (filled($pile->design_cutoff_level) && filled($pile->actual_cutoff_level)) {
            $cutoff = round(abs((float) bcsub((string) $pile->actual_cutoff_level, (string) $pile->design_cutoff_level, 3)), 3);
        } elseif (filled($pile->cut_off_level) && filled($pile->actual_toe_level)) {
            $cutoff = round(abs((float) bcsub((string) $pile->cut_off_level, (string) $pile->actual_toe_level, 3)), 3);
        }

        $measured = array_filter([$horizontal, $elevation, $cutoff], fn ($v) => $v !== null);
        if ($measured === []) {
            $status = 'NO_DATA';
        } else {
            $worst = max($measured);
            $status = $worst <= $tolerance ? 'PASS' : ($worst <= $tolerance * 2 ? 'WARNING' : 'OUT_OF_TOLERANCE');
        }

        return [
            'horizontal_deviation_m' => $horizontal,
            'elevation_deviation_m' => $elevation,
            'cutoff_deviation_m' => $cutoff,
            'status' => $status,
            'tolerance_m' => $tolerance,
        ];
    }
}
