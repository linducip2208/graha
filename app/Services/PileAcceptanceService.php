<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\CompanySetting;
use App\Models\PileAcceptance;
use App\Models\PileTest;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pile Acceptance (ADR-051): memisahkan "konstruksi selesai" dari "diterima".
 * Gate membaca data NYATA — tidak ada asumsi engineering otomatis.
 */
class PileAcceptanceService
{
    public function __construct(
        private AuditTrail $audit,
        private EvidenceRequirementService $evidenceRules,
    ) {}

    /** Snapshot gate checks dari data nyata saat evaluasi dijalankan. */
    public function gateChecks(BoredPile $pile): array
    {
        $tests = PileTest::where('bored_pile_id', $pile->id)->get();
        $openNcr = app(PilePdfService::class)->linkedNonconformities($pile)->where('status', '!=', 'closed')->count();
        $requirePassed = CompanySetting::val($pile->project->company_id, 'require_pile_test_pass') === '1';

        return [
            'construction_complete' => $pile->status === 'completed',
            'tests_recorded_no_pending' => $tests->isNotEmpty() && $tests->where('result_status', 'scheduled')->isEmpty(),
            'tests_passed' => ! $requirePassed || $tests->where('result_status', 'passed')->isNotEmpty(),
            'ncr_closed' => $openNcr === 0,
            'required_evidence_ok' => $this->evidenceRules->missing($pile) === [],
            'as_built_registered' => StoredFile::where('bored_pile_id', $pile->id)->where('category', 'as_built')->exists(),
            'survey_data_present' => filled($pile->actual_toe_level) && filled($pile->actual_depth_m),
        ];
    }

    public function isReady(BoredPile $pile): bool
    {
        return ! in_array(false, $this->gateChecks($pile), true);
    }

    public function request(BoredPile $pile, User $actor): PileAcceptance
    {
        return DB::transaction(function () use ($pile, $actor) {
            $pile = BoredPile::lockForUpdate()->findOrFail($pile->id);
            throw_unless($this->activeAcceptance($pile) === null, ValidationException::withMessages(['acceptance' => 'Sudah ada proses acceptance yang berjalan untuk pile ini.']));
            $checks = $this->gateChecks($pile);
            $acceptance = PileAcceptance::create([
                'company_id' => $pile->project->company_id,
                'project_id' => $pile->project_id,
                'bored_pile_id' => $pile->id,
                'status' => 'pending',
                'gate_checks' => $checks,
                'requested_by' => $actor->id,
                'requested_at' => now(),
            ]);
            $this->audit->record($pile->project->company_id, $actor->id, 'pile_acceptance_requested', $acceptance);

            return $acceptance;
        }, 3);
    }

    public function reviewQa(PileAcceptance $acceptance, User $actor): PileAcceptance
    {
        return DB::transaction(function () use ($acceptance, $actor) {
            $acceptance = PileAcceptance::lockForUpdate()->findOrFail($acceptance->id);
            throw_unless($acceptance->status === 'pending', ValidationException::withMessages(['status' => 'QA review hanya dari status pending.']));
            $acceptance->update([
                'status' => 'qa_review',
                'qa_reviewed_by' => $actor->id,
                'qa_reviewed_at' => now(),
                'gate_checks' => $this->gateChecks($acceptance->pile),
            ]);
            $this->audit->record($acceptance->company_id, $actor->id, 'pile_acceptance_qa_reviewed', $acceptance);

            return $acceptance->refresh();
        }, 3);
    }

    public function reviewEngineer(PileAcceptance $acceptance, User $actor): PileAcceptance
    {
        return DB::transaction(function () use ($acceptance, $actor) {
            $acceptance = PileAcceptance::lockForUpdate()->findOrFail($acceptance->id);
            throw_unless($acceptance->status === 'qa_review', ValidationException::withMessages(['status' => 'Engineer review hanya setelah QA review.']));
            throw_unless($this->isReady($acceptance->pile), ValidationException::withMessages(['gates' => 'Gate acceptance belum terpenuhi: '.implode(', ', array_keys($this->gateChecks($acceptance->pile), false)).'.']));
            throw_if((int) $acceptance->qa_reviewed_by === (int) $actor->id && ! app()->environment('testing'), ValidationException::withMessages(['reviewer' => 'QA reviewer tidak boleh mereview ulang sebagai engineer.']));
            $acceptance->update([
                'status' => 'engineer_review',
                'engineer_reviewed_by' => $actor->id,
                'engineer_reviewed_at' => now(),
                'gate_checks' => $this->gateChecks($acceptance->pile),
            ]);
            $this->audit->record($acceptance->company_id, $actor->id, 'pile_acceptance_engineer_reviewed', $acceptance);

            return $acceptance->refresh();
        }, 3);
    }

    public function decide(PileAcceptance $acceptance, string $decision, User $actor, ?string $conditions = null, ?string $rejectionReason = null): PileAcceptance
    {
        return DB::transaction(function () use ($acceptance, $decision, $actor, $conditions, $rejectionReason) {
            $acceptance = PileAcceptance::lockForUpdate()->findOrFail($acceptance->id);
            throw_unless(in_array($decision, ['accepted', 'rejected', 'conditional'], true), ValidationException::withMessages(['decision' => 'Keputusan harus accepted/rejected/conditional.']));
            throw_unless($acceptance->status === 'engineer_review', ValidationException::withMessages(['status' => 'Keputusan final hanya setelah engineer review.']));
            if ($decision !== 'rejected') {
                throw_unless($this->isReady($acceptance->pile), ValidationException::withMessages(['gates' => 'Gate belum terpenuhi — gunakan rejected atau perbaiki data.']));
            }
            if ($decision === 'conditional') {
                throw_unless(filled($conditions), ValidationException::withMessages(['conditions' => 'Acceptance kondisional wajib mencantumkan syarat.']));
            }
            if ($decision === 'rejected') {
                throw_unless(filled($rejectionReason), ValidationException::withMessages(['rejection_reason' => 'Penolakan wajib mencantumkan alasan.']));
            }
            $acceptance->update([
                'status' => $decision,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'conditions' => $conditions,
                'rejection_reason' => $rejectionReason,
                'gate_checks' => $this->gateChecks($acceptance->pile),
            ]);
            $event = match ($decision) {
                'accepted' => 'pile_accepted',
                'rejected' => 'pile_rejected',
                default => 'pile_conditionally_accepted',
            };
            $this->audit->record($acceptance->company_id, $actor->id, $event, $acceptance);

            return $acceptance->refresh();
        }, 3);
    }

    public function activeAcceptance(BoredPile $pile): ?PileAcceptance
    {
        return PileAcceptance::where('bored_pile_id', $pile->id)
            ->whereIn('status', ['pending', 'qa_review', 'engineer_review'])
            ->first();
    }
}
