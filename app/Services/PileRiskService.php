<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\BoredPileDrilling;
use App\Models\CompanySetting;
use App\Models\ConcreteDelivery;
use App\Models\PileTest;
use App\Models\StoredFile;

/**
 * Deterministic Pile Risk Engine (ADR-054). TANPA AI: skor dari aturan tetap
 * atas data nyata — depth mismatch, overbreak, slump gagal, interupsi beton,
 * cage QC gagal, uji gagal/belum ada, NCR terbuka, durasi abnormal, evidence
 * kurang (bila rules aktif). Output HEALTHY/WATCH/CRITICAL + alasan lengkap.
 */
class PileRiskService
{
    public function __construct(
        private EvidenceRequirementService $evidenceRules,
        private BoredPileGenealogyService $genealogy,
    ) {}

    /** @return array{level: string, score: int, reasons: array<int, array{code: string, severity: string, detail: string}>} */
    public function evaluate(BoredPile $pile): array
    {
        $reasons = [];
        $companyId = $pile->project->company_id;
        $depthTolerance = max(0.5, (float) CompanySetting::val($companyId, 'pile_depth_tolerance_percent'));
        $overbreakTolerance = max(1.0, (float) ($pile->project->overbreak_tolerance_percent ?? CompanySetting::val($companyId, 'default_overbreak_tolerance_percent')));

        // 1. Depth mismatch.
        if ($pile->actual_depth_m !== null && bccomp((string) $pile->planned_depth_m, '0', 3) === 1) {
            $deviation = abs((float) bcdiv(bcmul(bcsub((string) $pile->actual_depth_m, (string) $pile->planned_depth_m, 3), '100', 4), (string) $pile->planned_depth_m, 4));
            if ($deviation > $depthTolerance) {
                $reasons[] = ['code' => 'depth_mismatch', 'severity' => 'warning', 'detail' => "Kedalaman menyimpang {$deviation}% (toleransi {$depthTolerance}%)."];
            }
        }

        // 2. Overbreak.
        if ($pile->overbreak_exceeded) {
            $severity = (float) $pile->overbreak_percent > 2 * $overbreakTolerance ? 'critical' : 'warning';
            $reasons[] = ['code' => 'concrete_overbreak', 'severity' => $severity, 'detail' => "Overbreak {$pile->overbreak_percent}% melebihi toleransi {$overbreakTolerance}."];
        }

        // 3. Slump di luar spesifikasi.
        $min = CompanySetting::val($companyId, 'slump_min_cm');
        $max = CompanySetting::val($companyId, 'slump_max_cm');
        $deliveries = ConcreteDelivery::where('bored_pile_id', $pile->id)->where('status', 'approved')->orderBy('arrived_at')->get();
        foreach ($deliveries as $delivery) {
            if ($delivery->slump_cm !== null && bccomp($min, $max, 2) !== 1 && (bccomp((string) $delivery->slump_cm, $min, 2) === -1 || bccomp((string) $delivery->slump_cm, $max, 2) === 1)) {
                $reasons[] = ['code' => 'slump_out_of_spec', 'severity' => 'warning', 'detail' => "DO {$delivery->delivery_order_number}: slump {$delivery->slump_cm} cm di luar {$min}–{$max} cm."];
            }
        }

        // 4. Interupsi antar-truk (gap > toleransi).
        $maxGap = (int) CompanySetting::val($companyId, 'concrete_max_gap_minutes');
        $windows = $deliveries->filter(fn ($d) => $d->pour_started_at !== null)->values();
        foreach ($windows as $i => $delivery) {
            if ($i === 0) {
                continue;
            }
            $previous = $windows[$i - 1];
            if ($previous->pour_finished_at !== null) {
                $gapMinutes = (int) ($previous->pour_finished_at->diffInMinutes($delivery->pour_started_at, false));
                if ($gapMinutes > $maxGap) {
                    $reasons[] = ['code' => 'concrete_interruption', 'severity' => 'warning', 'detail' => "Jeda antar-truk {$gapMinutes} menit melebihi batas {$maxGap} menit (setelah DO {$previous->delivery_order_number})."];
                }
            }
        }

        // 5. Pengujian.
        $tests = PileTest::where('bored_pile_id', $pile->id)->get();
        foreach ($tests->where('result_status', 'failed') as $test) {
            $reasons[] = ['code' => 'test_failed', 'severity' => 'critical', 'detail' => "Uji {$test->test_type} {$test->number} berstatus FAILED".($test->ncr_number ? ' (NCR '.$test->ncr_number.')' : '').'.'];
        }
        if (in_array($pile->status, ['testing', 'completed'], true) && $tests->isEmpty()) {
            $reasons[] = ['code' => 'missing_test', 'severity' => 'warning', 'detail' => 'Belum ada pengujian padahal fase testing/completed.'];
        }

        // 6. NCR terbuka yang tertaut ke pile ini.
        foreach (app(PilePdfService::class)->linkedNonconformities($pile)->where('status', '!=', 'closed') as $ncr) {
            $reasons[] = ['code' => 'open_ncr', 'severity' => $ncr->severity === 'critical' ? 'critical' : 'warning', 'detail' => "NCR {$ncr->number} masih {$ncr->status}."];
        }

        // 7. Durasi drilling abnormal vs median proyek (faktor 3×).
        $abnormal = $this->abnormalDrillingDuration($pile);
        if ($abnormal !== null) {
            $reasons[] = ['code' => 'abnormal_duration', 'severity' => 'warning', 'detail' => "Durasi drilling {$abnormal['hours']} jam jauh di atas median proyek {$abnormal['median']} jam."];
        }

        // 8. Evidence kurang (hanya bila rules company aktif).
        if ($this->evidenceRules->enabled($companyId)) {
            foreach ($this->evidenceRules->missing($pile) as $category => $info) {
                $reasons[] = ['code' => 'missing_evidence', 'severity' => 'warning', 'detail' => 'Foto '.(StoredFile::PHOTO_CATEGORIES[$category] ?? $category)." kurang: {$info['actual']}/{$info['required']}."];
            }
        }

        // Anomali genealogy existing yang belum tercakup (cage QC gagal dsb).
        $data = $this->genealogy->build($pile);
        foreach ($data['anomalies'] as $flag) {
            if ($flag['code'] === 'rejected_cage') {
                $reasons[] = ['code' => 'cage_qc_failed', 'severity' => 'critical', 'detail' => $flag['detail']];
            }
        }

        [$level, $score] = $this->score($reasons);

        return ['level' => $level, 'score' => $score, 'reasons' => $reasons];
    }

    private function score(array $reasons): array
    {
        $weights = ['critical' => 40, 'warning' => 15];
        $score = collect($reasons)->sum(fn ($r) => $weights[$r['severity']] ?? 10);
        if (collect($reasons)->contains('severity', 'critical') || $score >= 60) {
            return ['critical', $score];
        }
        if ($score >= 15) {
            return ['watch', $score];
        }

        return ['healthy', $score];
    }

    private function abnormalDrillingDuration(BoredPile $pile): ?array
    {
        $projectMedianHours = BoredPileDrilling::query()
            ->join('bored_piles', 'bored_piles.id', '=', 'bored_pile_drillings.bored_pile_id')
            ->where('bored_piles.project_id', $pile->project_id)
            ->whereNotNull('drilling_finished_at')
            ->get()
            ->map(fn ($d) => max(0.01, $d->drilling_started_at?->diffInHours($d->drilling_finished_at) ?? 0))
            ->sort()
            ->values();
        if ($projectMedianHours->count() < 3) {
            return null; // Data tidak cukup — jangan mengarang baseline.
        }
        $median = $projectMedianHours->median();

        $own = BoredPileDrilling::where('bored_pile_id', $pile->id)
            ->whereNotNull('drilling_finished_at')->get()
            ->map(fn ($d) => $d->drilling_started_at?->diffInHours($d->drilling_finished_at) ?? 0)
            ->sum();
        if ($own > $median * 3 && $own > 0) {
            return ['hours' => round($own, 1), 'median' => round((float) $median, 1)];
        }

        return null;
    }
}
