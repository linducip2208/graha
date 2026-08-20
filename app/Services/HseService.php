<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\HseIncident;
use App\Models\HseIncidentAction;
use App\Models\JobSafetyAnalysis;
use App\Models\User;
use App\Models\WorkPermit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HseService
{
    public function __construct(private AuditTrail $audit) {}

    public function activateApprovedJsa(JobSafetyAnalysis $jsa, User $actor): JobSafetyAnalysis
    {
        return DB::transaction(function () use ($jsa, $actor) {
            $jsa = JobSafetyAnalysis::lockForUpdate()->findOrFail($jsa->id);
            $approved = ApprovalRequest::where('approvable_type', JobSafetyAnalysis::class)->where('approvable_id', $jsa->id)->where('status', 'approved')->exists();
            throw_unless($approved, ValidationException::withMessages(['approval' => 'JSA belum disetujui.']));
            $jsa->update(['status' => 'approved']);
            $this->audit->record($jsa->company_id, $actor->id, 'hse.jsa_approved', $jsa);

            return $jsa->refresh();
        }, 3);
    }

    public function issuePermit(JobSafetyAnalysis $jsa, array $data, User $actor): WorkPermit
    {
        return DB::transaction(function () use ($jsa, $data, $actor) {
            $jsa = JobSafetyAnalysis::lockForUpdate()->findOrFail($jsa->id);
            throw_unless($jsa->status === 'approved' && $jsa->valid_from->lte($data['valid_from']) && $jsa->valid_until->endOfDay()->gte($data['valid_until']), ValidationException::withMessages(['jsa' => 'JSA tidak approved atau periode permit di luar validitas JSA.']));
            $permit = WorkPermit::create([...$data, 'company_id' => $jsa->company_id, 'project_id' => $jsa->project_id, 'job_safety_analysis_id' => $jsa->id, 'issued_by' => $actor->id, 'status' => 'issued']);
            $this->audit->record($jsa->company_id, $actor->id, 'hse.permit_issued', $permit);

            return $permit;
        }, 3);
    }

    public function verifyAction(HseIncidentAction $action, User $verifier): HseIncidentAction
    {
        throw_if($action->owner_id === $verifier->id, ValidationException::withMessages(['verifier' => 'PIC tidak boleh memverifikasi tindakan sendiri.']));
        throw_if(blank($action->evidence), ValidationException::withMessages(['evidence' => 'Evidence wajib tersedia.']));
        $action->update(['status' => 'effective', 'verified_by' => $verifier->id, 'verified_at' => now()]);

        return $action->refresh();
    }

    public function closeIncident(HseIncident $incident, User $actor): HseIncident
    {
        return DB::transaction(function () use ($incident, $actor) {
            $incident = HseIncident::with('actions')->lockForUpdate()->findOrFail($incident->id);
            throw_if(blank($incident->root_cause), ValidationException::withMessages(['root_cause' => 'Root cause wajib tersedia.']));
            throw_if($incident->actions->isEmpty() || $incident->actions->contains(fn ($action) => $action->status !== 'effective'), ValidationException::withMessages(['actions' => 'Semua tindakan wajib terverifikasi efektif.']));
            $incident->update(['status' => 'closed', 'closed_at' => now()]);
            $this->audit->record($incident->company_id, $actor->id, 'hse.incident_closed', $incident);

            return $incident->refresh();
        }, 3);
    }
}
