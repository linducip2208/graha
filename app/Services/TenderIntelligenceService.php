<?php

namespace App\Services;

use App\Models\Competitor;
use App\Models\Tender;
use App\Models\TenderParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenderIntelligenceService
{
    public function addParticipant(Tender $tender, array $data, User $actor): TenderParticipant
    {
        return DB::transaction(function () use ($tender, $data, $actor) {
            throw_unless($actor->companies()->whereKey($tender->company_id)->where('company_user.is_active', true)->exists(), ValidationException::withMessages(['company' => 'Anda bukan anggota aktif perusahaan tender ini.']));
            throw_unless(Tender::where('company_id', $tender->company_id)->whereKey($tender->id)->exists(), ValidationException::withMessages(['tender' => 'Tender tidak valid.']));
            if (! empty($data['competitor_id'])) {
                throw_unless(Competitor::where('company_id', $tender->company_id)->whereKey($data['competitor_id'])->exists(), ValidationException::withMessages(['competitor_id' => 'Kompetitor tidak ditemukan di perusahaan ini.']));
            }
            if (! empty($data['is_winner'])) {
                TenderParticipant::where('tender_id', $tender->id)->where('is_winner', true)->update(['is_winner' => false]);
            }

            return TenderParticipant::create([...$data, 'company_id' => $tender->company_id, 'tender_id' => $tender->id, 'recorded_by' => $actor->id]);
        }, 3);
    }

    public function registerCompetitor(int $companyId, array $data): Competitor
    {
        return Competitor::firstOrCreate(['company_id' => $companyId, 'code' => $data['code']], ['name' => $data['name'], 'notes' => $data['notes'] ?? null]);
    }

    /** Win/loss stats. Exclude: draft, cancelled, no_bid, dan status belum diputuskan (bidding). */
    public function stats(int $companyId): array
    {
        $decided = Tender::where('company_id', $companyId)->whereIn('status', ['won', 'lost']);
        $won = (int) (clone $decided)->where('status', 'won')->count();
        $lost = (int) (clone $decided)->where('status', 'lost')->count();
        $decidedCount = $won + $lost;

        $wonTenders = Tender::where('company_id', $companyId)->where('status', 'won')->get(['id', 'owner_estimate', 'bid_value']);
        $lostOutcomes = DB::table('tender_outcomes')->join('tenders', 'tenders.id', '=', 'tender_outcomes.tender_id')
            ->where('tenders.company_id', $companyId)->where('tender_outcomes.outcome', 'lost')
            ->selectRaw('SUM(winning_bid_value) as lost_value, AVG(NULLIF(winning_bid_value,0)) as avg_winning')
            ->first();

        $priceDiffPcts = $wonTenders->filter(fn ($t) => $t->owner_estimate && bccomp((string) $t->owner_estimate, '0', 2) === 1)
            ->map(fn ($t) => ((float) bcdiv(bcmul(bcsub((string) $t->bid_value, (string) $t->owner_estimate, 2), '100', 4), (string) $t->owner_estimate, 4)));
        $avgVsHps = $priceDiffPcts->isEmpty() ? null : round($priceDiffPcts->avg(), 2);

        $topWinner = TenderParticipant::where('company_id', $companyId)->where('is_winner', true)
            ->selectRaw('name, COUNT(*) as total')->groupBy('name')->orderByDesc('total')->first();

        $outcomeRows = DB::table('tender_outcomes')->join('tenders', 'tenders.id', '=', 'tender_outcomes.tender_id')
            ->where('tenders.company_id', $companyId)
            ->orderBy('announced_at')
            ->limit(500)
            ->get(['announced_at', 'outcome']);
        $monthly = $outcomeRows
            ->groupBy(fn ($row) => substr((string) $row->announced_at, 0, 7))
            ->map(function ($rows, $ym) {
                $won = $rows->filter(fn ($r) => $r->outcome === 'won')->count();
                $lost = $rows->filter(fn ($r) => $r->outcome === 'lost')->count();

                return ['ym' => $ym, 'won' => $won, 'lost' => $lost];
            })
            ->sortKeys()
            ->take(12)
            ->values();

        return [
            'submitted' => Tender::where('company_id', $companyId)->whereIn('status', ['bidding', 'submitted'])->count(),
            'won' => $won,
            'lost' => $lost,
            'win_rate' => $decidedCount > 0 ? round($won / $decidedCount * 100, 1) : null,
            'loss_rate' => $decidedCount > 0 ? round($lost / $decidedCount * 100, 1) : null,
            'won_value' => $wonTenders->reduce(fn ($carry, $t) => bcadd($carry, (string) $t->bid_value, 2), '0'),
            'lost_opportunity' => (string) ($lostOutcomes->lost_value ?? '0'),
            'avg_vs_hps_pct' => $avgVsHps,
            'top_competitor' => $topWinner?->name,
            'top_competitor_wins' => $topWinner?->total ?? 0,
            'monthly' => $monthly,
        ];
    }
}
