<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Tender;
use App\Models\TenderOutcome;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenderService
{
    public function __construct(private AuditTrail $audit, private NumberSequenceService $numbers) {}

    public function recordOutcome(Tender $tender, User $actor, string $outcome, array $data): TenderOutcome
    {
        return DB::transaction(function () use ($tender, $actor, $outcome, $data) {
            $tender = Tender::lockForUpdate()->findOrFail($tender->id);
            throw_unless(in_array($outcome, ['won', 'lost'], true), ValidationException::withMessages(['outcome' => 'Hasil tender tidak valid.']));
            throw_if($tender->outcome()->exists(), ValidationException::withMessages(['outcome' => 'Hasil tender sudah dicatat.']));
            if ($outcome === 'lost' && empty($data['primary_reason'])) {
                throw ValidationException::withMessages(['primary_reason' => 'Alasan kalah wajib diisi.']);
            }$record = $tender->outcome()->create([...$data, 'outcome' => $outcome, 'recorded_by' => $actor->id]);
            $tender->update(['status' => $outcome]);
            $this->audit->record($tender->company_id, $actor->id, 'tender.'.$outcome, $tender);

            return $record;
        }, 3);
    }

    public function convertWonToProject(Tender $tender, User $actor): Project
    {
        return DB::transaction(function () use ($tender, $actor) {
            $tender = Tender::with('outcome')->lockForUpdate()->findOrFail($tender->id);
            if ($existing = Project::where('source_tender_id', $tender->id)->first()) {
                return $existing;
            }throw_unless($tender->status === 'won' && $tender->outcome?->outcome === 'won', ValidationException::withMessages(['tender' => 'Hanya tender menang yang dapat dikonversi.']));
            $project = Project::create(['company_id' => $tender->company_id, 'branch_id' => $tender->branch_id, 'customer_id' => $tender->customer_id, 'source_tender_id' => $tender->id, 'code' => $this->numbers->next($tender->company_id, 'project'), 'name' => $tender->project_name, 'location' => $tender->location, 'contract_value' => $tender->outcome->contract_value ?? $tender->bid_value, 'estimated_cost' => $tender->estimated_cost]);
            $this->audit->record($tender->company_id, $actor->id, 'tender.converted_to_project', $project);

            return $project;
        }, 3);
    }

    public function metrics(int $companyId, int $year): array
    {
        $base = Tender::where('company_id', $companyId)->where('year', $year);
        $won = (clone $base)->where('status', 'won')->count();
        $lost = (clone $base)->where('status', 'lost')->count();
        $decided = $won + $lost;

        return ['followed' => (clone $base)->count(), 'won' => $won, 'lost' => $lost, 'win_rate' => $decided ? round($won / $decided * 100, 2) : 0.0, 'loss_rate' => $decided ? round($lost / $decided * 100,2) : 0.0];
    }
}
