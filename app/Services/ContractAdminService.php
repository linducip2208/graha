<?php

namespace App\Services;

use App\Models\ContractInsurance;
use App\Models\ContractMilestone;
use App\Models\ProjectAward;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Administrasi kontrak (ADR-062): milestone register dengan bobot progres dan asuransi kontrak. */
class ContractAdminService
{
    public function __construct(private AuditTrail $audit) {}

    public function addMilestone(ProjectAward $award, array $data, User $actor): ContractMilestone
    {
        return DB::transaction(function () use ($award, $data, $actor) {
            throw_unless((int) $award->company_id === (int) $data['company_id'], ValidationException::withMessages(['award' => 'Kontrak tidak valid.']));
            $existingWeight = (string) ContractMilestone::where('project_award_id', $award->id)->sum('weight_percent');
            throw_if(bccomp(bcadd($existingWeight, $data['weight_percent'], 3), '100', 3) === 1, ValidationException::withMessages(['weight_percent' => "Total bobot milestone melebihi 100% (sudah terpakai {$existingWeight}%)."]));
            $milestone = ContractMilestone::create([...$data, 'project_award_id' => $award->id]);
            $this->audit->record($award->company_id, $actor->id, 'tender.contract_milestone_added', $milestone);

            return $milestone;
        }, 3);
    }

    public function achieveMilestone(ContractMilestone $milestone, string $actualDate, User $actor): ContractMilestone
    {
        return DB::transaction(function () use ($milestone, $actualDate, $actor) {
            $milestone = ContractMilestone::lockForUpdate()->findOrFail($milestone->id);
            throw_unless($milestone->status === 'pending', ValidationException::withMessages(['status' => 'Milestone sudah dicapai.']));
            $milestone->update(['status' => 'achieved', 'actual_date' => $actualDate]);
            $this->audit->record($milestone->company_id, $actor->id, 'tender.contract_milestone_achieved', $milestone);

            return $milestone;
        }, 3);
    }

    public function addInsurance(ProjectAward $award, array $data, User $actor): ContractInsurance
    {
        return DB::transaction(function () use ($award, $data, $actor) {
            throw_unless((int) $award->company_id === (int) $data['company_id'], ValidationException::withMessages(['award' => 'Kontrak tidak valid.']));
            throw_if(strtotime($data['end_date']) < strtotime($data['start_date']), ValidationException::withMessages(['end_date' => 'Tanggal akhir polis sebelum tanggal mulai.']));
            $insurance = ContractInsurance::create([...$data, 'project_award_id' => $award->id, 'created_by' => $actor->id]);
            $this->audit->record($award->company_id, $actor->id, 'tender.contract_insurance_added', $insurance);

            return $insurance;
        }, 3);
    }
}
