<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\FoundationGroup;
use App\Models\Nonconformity;
use App\Models\PileTest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P11 — Pile cap / group readiness (ADR-084): deterministik dari data nyata,
 * TANPA structural design check. Output READY/NOT_READY + exception list.
 *
 * Checklist:
 * 1. Pile terkonfigurasi (grup tidak kosong).
 * 2. Semua pile konstruksi complete.
 * 3. Semua pile accepted (acceptance terakhir berstatus accepted).
 * 4. NCR kritis terbuka = 0 (NCR tertaut via ncr_number pada pile test).
 * 5. Tidak ada uji yang masih scheduled/pending.
 * 6. Survey acceptance: setiap pile punya koordinat aktual terisi dan deviasi
 *    survey tidak OUT_OF_TOLERANCE.
 */
class FoundationGroupService
{
    public function __construct(
        private PileSurveyService $survey,
        private AuditTrail $audit,
    ) {}

    /** @return array{status: string, exceptions: array<int, string>, checks: array<string, bool>, piles: array<int, array{pile: BoredPile, issues: array<int, string>}>, checked_at: string} */
    public function readiness(FoundationGroup $group): array
    {
        $group->loadMissing('piles.acceptance');
        $piles = $group->piles;

        $exceptions = [];
        $pilesReport = [];
        $allComplete = true;
        $allAccepted = true;
        $noCriticalNcr = true;
        $testsSettled = true;
        $surveyOk = true;

        if ($piles->isEmpty()) {
            $exceptions[] = 'Grup belum memuat satu pun pile.';
        }

        foreach ($piles as $pile) {
            $issues = [];

            // 2. Konstruksi complete.
            if ($pile->status !== 'completed') {
                $allComplete = false;
                $issues[] = 'status '.str_replace('_', ' ', $pile->status).' (belum completed)';
            }

            // 3. Accepted.
            $acceptanceStatus = $pile->acceptance?->status;
            if ($acceptanceStatus !== 'accepted') {
                $allAccepted = false;
                $issues[] = 'acceptance '.($acceptanceStatus ? str_replace('_', ' ', $acceptanceStatus) : 'belum diajukan');
            }

            // 4. NCR kritis terbuka tertaut ke pile ini.
            $ncrNumbers = PileTest::where('bored_pile_id', $pile->id)->whereNotNull('ncr_number')->pluck('ncr_number')->unique();
            if ($ncrNumbers->isNotEmpty()) {
                $openCritical = Nonconformity::where('company_id', $group->company_id)
                    ->where('project_id', $pile->project_id)
                    ->whereIn('number', $ncrNumbers)
                    ->where('severity', 'critical')
                    ->where('status', '!=', 'closed')
                    ->count();
                if ($openCritical > 0) {
                    $noCriticalNcr = false;
                    $issues[] = "{$openCritical} NCR kritis terbuka";
                }
            }

            // 5. Uji belum tuntas.
            $pendingTests = PileTest::where('bored_pile_id', $pile->id)->where('result_status', 'scheduled')->count();
            if ($pendingTests > 0) {
                $testsSettled = false;
                $issues[] = "{$pendingTests} uji menunggu hasil";
            }

            // 6. Survey aktual tersedia + dalam toleransi.
            $deviation = $this->survey->deviation($pile);
            if ($deviation['status'] === 'NO_DATA') {
                $surveyOk = false;
                $issues[] = 'data survey aktual belum ada';
            } elseif ($deviation['status'] === 'OUT_OF_TOLERANCE') {
                $surveyOk = false;
                $issues[] = 'deviasi survey OUT_OF_TOLERANCE';
            }

            $pilesReport[] = ['pile' => $pile, 'issues' => $issues];
        }

        if (! $allComplete) {
            $exceptions[] = 'Masih ada pile yang belum construction complete.';
        }
        if (! $allAccepted) {
            $exceptions[] = 'Masih ada pile yang belum accepted.';
        }
        if (! $noCriticalNcr) {
            $exceptions[] = 'Masih ada NCR kritis terbuka pada anggota grup.';
        }
        if (! $testsSettled) {
            $exceptions[] = 'Masih ada pengujian yang belum direkam hasilnya.';
        }
        if (! $surveyOk) {
            $exceptions[] = 'Survey acceptance belum lengkap/dalam toleransi.';
        }

        return [
            'status' => ($piles->isNotEmpty() && $allComplete && $allAccepted && $noCriticalNcr && $testsSettled && $surveyOk)
                ? 'READY'
                : 'NOT_READY',
            'exceptions' => $exceptions,
            'checks' => [
                'piles_configured' => $piles->isNotEmpty(),
                'all_completed' => $allComplete,
                'all_accepted' => $allAccepted,
                'no_critical_ncr' => $noCriticalNcr,
                'tests_settled' => $testsSettled,
                'survey_complete' => $surveyOk,
            ],
            'piles' => $pilesReport,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /** Readiness untuk semua grup proyek sekaligus (anti-N+1 per render). */
    public function readinessForProject(int $projectId): Collection
    {
        return FoundationGroup::where('project_id', $projectId)
            ->with(['piles.acceptance'])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (FoundationGroup $group) => [$group->id => $this->readiness($group)]);
    }

    public function attachPile(FoundationGroup $group, BoredPile $pile): void
    {
        throw_unless($pile->project_id === $group->project_id, ValidationException::withMessages(['bored_pile_id' => 'Pile bukan milik proyek grup ini.']));
        $group->piles()->syncWithoutDetaching([$pile->id => ['sequence' => ((int) DB::table('foundation_group_piles')->where('foundation_group_id', $group->id)->max('sequence')) + 1]]);
        $this->audit->record($group->company_id, auth()->id(), 'foundation_group_pile_attached', $group);
    }

    public function detachPile(FoundationGroup $group, BoredPile $pile): void
    {
        $group->piles()->detach([$pile->id]);
        $this->audit->record($group->company_id, auth()->id(), 'foundation_group_pile_detached', $group);
    }
}
