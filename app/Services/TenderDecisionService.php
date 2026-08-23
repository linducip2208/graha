<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Bid / No-Bid Decision (ADR-048): scoring faktor nyata yang tersedia
 * (margin estimasi, cover HPS, kompetisi, termin pelanggan) dengan bobot
 * & ambang configurable perusahaan. Faktor tanpa data TIDAK dikarang —
 * hasilnya "Perlu Review" disertai alasan. AI tidak dipakai.
 */
class TenderDecisionService
{
    public function __construct(private AuditTrail $audit) {}

    public const RECOMMEND_BID = 'recommended_bid';

    public const REVIEW_REQUIRED = 'review_required';

    public const RECOMMEND_NO_BID = 'recommended_no_bid';

    public const FACTOR_LABELS = [
        'margin' => 'Margin Estimasi',
        'hps_cover' => 'Cover HPS Pemilik',
        'competition' => 'Kompetisi',
        'payment' => 'Termin Pelanggan',
    ];

    public function evaluate(Tender $tender, User $actor): array
    {
        $companyId = $tender->company_id;
        $factors = [];
        $reasons = [];

        // 1. Margin: estimasi terakhir (BOQ vs RAP), fallback tender fields.
        [$marginPct, $marginSource] = $this->resolveMargin($tender);
        if ($marginPct === null) {
            $reasons[] = 'Belum ada estimasi (BOQ/RAP) maupun owner estimate + estimated cost.';
        } else {
            $factors['margin'] = [
                'value' => round((float) $marginPct, 2),
                'display' => number_format((float) $marginPct, 1, ',', '.').'% ('.$marginSource.')',
                'score' => max(0, min(100, round((float) $marginPct / 25 * 100))),
            ];
        }

        // 2. Cover HPS: owner_estimate dibagi biaya kita (RAP total / estimated_cost).
        $ourCost = $this->ourCost($tender);
        if ($tender->owner_estimate !== null && bccomp((string) $tender->owner_estimate, '0', 2) === 1 && $ourCost !== null && bccomp($ourCost, '0', 2) === 1) {
            $cover = (float) bcdiv((string) $tender->owner_estimate, $ourCost, 4);
            $score = (int) max(0, min(100, round(($cover - 0.85) / 0.35 * 100)));
            $factors['hps_cover'] = [
                'value' => $cover,
                'display' => number_format($cover, 2, ',', '.').'×',
                'score' => $score,
            ];
        } else {
            $reasons[] = 'Cover HPS tidak dapat dihitung (owner estimate atau biaya internal kosong).';
        }

        // 3. Kompetisi: jumlah peserta lain; pemenang sudah ditentukan = penalti besar.
        $others = (int) (clone $tender)->participants()->where('is_winner', false)->count() ?: $tender->participants()->count();
        if ($tender->participants()->count() > 0 || $others > 0) {
            $score = match (true) {
                $others <= 2 => 90,
                $others <= 4 => 60,
                default => 30,
            };
            if ($tender->participants()->where('is_winner', true)->exists()) {
                $score = max(5, $score - 45);
            }
            $factors['competition'] = ['value' => $others, 'display' => $others.' peserta lain', 'score' => $score];
        } else {
            $reasons[] = 'Jumlah peserta/kompetitor belum dicatat.';
        }

        // 4. Termin pelanggan.
        $term = $tender->customer?->payment_term_days;
        if ($term !== null) {
            $score = match (true) {
                $term <= 30 => 100,
                $term <= 45 => 85,
                $term <= 60 => 65,
                $term <= 90 => 40,
                default => 20,
            };
            $factors['payment'] = ['value' => $term, 'display' => $term.' hari', 'score' => $score];
        } else {
            $reasons[] = 'Termin pembayaran pelanggan belum diatur.';
        }

        // Bobot configurable; bobot faktor yang tak tersedia direalokasi proporsional.
        $weights = [
            'margin' => max(0, (float) CompanySetting::val($companyId, 'bid_weight_margin')),
            'hps_cover' => max(0, (float) CompanySetting::val($companyId, 'bid_weight_hps')),
            'competition' => max(0, (float) CompanySetting::val($companyId, 'bid_weight_competition')),
            'payment' => max(0, (float) CompanySetting::val($companyId, 'bid_weight_payment')),
        ];
        $availableWeight = 0.0;
        foreach ($factors as $key => $factor) {
            $factor['weight'] = $weights[$key];
            $availableWeight += $weights[$key];
            $factors[$key] = $factor;
        }
        $weighted = 0.0;
        foreach ($factors as $key => $factor) {
            $share = $availableWeight > 0 ? $factor['weight'] / $availableWeight : 0.0;
            $factors[$key]['weight_share'] = round($share * 100);
            $weighted += $factor['score'] * $share;
        }
        $score = (int) round($weighted);

        $recommendReview = in_array('margin', array_keys(self::FACTOR_LABELS), true) && ! isset($factors['margin']);
        $thresholdBid = (float) CompanySetting::val($companyId, 'bid_threshold_recommended');
        $thresholdNoBid = (float) CompanySetting::val($companyId, 'bid_threshold_no_bid');
        $recommendation = $recommendReview
            ? self::REVIEW_REQUIRED
            : ($score >= $thresholdBid ? self::RECOMMEND_BID : ($score < $thresholdNoBid ? self::RECOMMEND_NO_BID : self::REVIEW_REQUIRED));

        $decision = [
            'score' => $score,
            'recommendation' => $recommendation,
            'reasons' => $reasons,
            'thresholds' => ['bid' => $thresholdBid, 'no_bid' => $thresholdNoBid],
            'factors' => collect($factors)->map(fn ($f, $key) => [
                'key' => $key,
                'label' => self::FACTOR_LABELS[$key],
                'value' => $f['value'],
                'display' => $f['display'],
                'score' => $f['score'],
                'weight_share' => $f['weight_share'],
            ])->values()->all(),
            'evaluated_at' => now()->toIso8601String(),
            'evaluated_by' => $actor->id,
        ];

        DB::transaction(function () use ($tender, $decision, $actor): void {
            $tender->update([
                'bid_decision_json' => $decision,
                'bid_decision_at' => now(),
                'bid_decision_by' => $actor->id,
            ]);
            $this->audit->record($tender->company_id, $actor->id, 'tender.bid_decision', $tender);
        }, 3);

        return $decision;
    }

    /** Analitik alasan kalah: agregasi primary_reason + elimination_stage dari outcome lost. */
    public function lossAnalysis(int $companyId): array
    {
        $rows = DB::table('tender_outcomes')
            ->join('tenders', 'tenders.id', '=', 'tender_outcomes.tender_id')
            ->where('tenders.company_id', $companyId)
            ->where('tender_outcomes.outcome', 'lost')
            ->groupBy('tender_outcomes.primary_reason', 'tender_outcomes.elimination_stage')
            ->selectRaw("COALESCE(tender_outcomes.primary_reason,'unknown') as reason")
            ->selectRaw("COALESCE(tender_outcomes.elimination_stage,'-') as stage")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(tender_outcomes.winning_bid_value),0) as value')
            ->orderByDesc('total')
            ->get();

        return [
            'by_reason' => $rows->groupBy('reason')->map(fn ($g) => [
                'total' => $g->sum('total'),
                'value' => (float) $g->sum('value'),
            ])->sortDesc()->sortByDesc(fn ($r) => $r['total'])->all(),
            'by_stage' => $rows->groupBy('stage')->map(fn ($g) => $g->sum('total'))->sortDesc()->all(),
            'total_lost' => $rows->sum('total'),
        ];
    }

    private function resolveMargin(Tender $tender): array
    {
        $estimate = $tender->estimate()->orderByDesc('version')->first();
        if ($estimate && bccomp((string) $estimate->boq_total, '0', 2) === 1) {
            $pct = bcmul(bcdiv(bcsub((string) $estimate->boq_total, (string) $estimate->rap_total, 2), (string) $estimate->boq_total, 6), '100', 4);

            return [$pct, 'estimasi v'.$estimate->version];
        }
        if ($tender->owner_estimate && bccomp((string) $tender->owner_estimate, '0', 2) === 1 && $tender->estimated_cost !== null) {
            $pct = bcmul(bcdiv(bcsub((string) $tender->owner_estimate, (string) $tender->estimated_cost, 2), (string) $tender->owner_estimate, 6), '100', 4);

            return [$pct, 'owner estimate vs cost'];
        }

        return [null, null];
    }

    private function ourCost(Tender $tender): ?string
    {
        $estimate = $tender->estimate()->orderByDesc('version')->first();
        if ($estimate && bccomp((string) $estimate->rap_total, '0', 2) === 1) {
            return (string) $estimate->rap_total;
        }
        if ($tender->estimated_cost !== null && bccomp((string) $tender->estimated_cost, '0', 2) === 1) {
            return (string) $tender->estimated_cost;
        }

        return null;
    }
}
