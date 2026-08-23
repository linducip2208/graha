<?php

namespace App\Services;

use App\Models\CorrectiveAction;
use App\Models\Document;
use App\Models\InternalAudit;
use App\Models\Nonconformity;
use App\Models\QmsComplianceMapping;
use App\Models\RiskOpportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QmsService
{
    public function __construct(private AuditTrail $audit) {}

    public function createRisk(array $data, User $actor): RiskOpportunity
    {
        $likelihood = (int) $data['likelihood'];
        $impact = (int) $data['impact'];
        throw_unless($likelihood >= 1 && $likelihood <= 5 && $impact >= 1 && $impact <= 5, ValidationException::withMessages(['risk' => 'Likelihood dan impact harus 1-5.']));
        $risk = RiskOpportunity::create([...$data, 'inherent_score' => $likelihood * $impact]);
        $this->audit->record($risk->company_id, $actor->id, 'qms.risk_created', $risk);

        return $risk;
    }

    public function verifyCapa(CorrectiveAction $action, User $verifier, string $notes): CorrectiveAction
    {
        return DB::transaction(function () use ($action, $verifier, $notes) {
            $action = CorrectiveAction::lockForUpdate()->findOrFail($action->id);
            throw_if($action->owner_id === $verifier->id, ValidationException::withMessages(['verifier' => 'Pemilik tindakan tidak boleh memverifikasi tindakannya sendiri.']));
            throw_if(empty($action->evidence), ValidationException::withMessages(['evidence' => 'Evidence wajib tersedia.']));
            $action->update(['status' => 'effective', 'verified_by' => $verifier->id, 'verified_at' => now(), 'effectiveness_notes' => $notes]);
            $ncr = Nonconformity::with('actions')->findOrFail($action->nonconformity_id);
            // Registry: CAPA efektif dan NCR tertutup otomatis terdaftar sebagai dokumen (idempotent).
            Document::firstOrCreate(
                ['company_id' => $ncr->company_id, 'document_type' => 'corrective_action', 'number' => $ncr->number.'-CA'.$action->id],
                ['title' => 'CAPA '.$ncr->number.' — '.Str::limit($action->action, 60), 'owner_id' => $verifier->id, 'workflow_status' => 'approved', 'signature_status' => 'unsigned']
            );
            if ($ncr->actions->every(fn ($item) => $item->status === 'effective')) {
                $ncr->update(['status' => 'closed']);
                Document::firstOrCreate(
                    ['company_id' => $ncr->company_id, 'document_type' => 'nonconformity_report', 'number' => $ncr->number],
                    ['title' => 'NCR '.$ncr->number.' — '.Str::limit($ncr->description, 80), 'owner_id' => $verifier->id, 'workflow_status' => 'approved', 'signature_status' => 'unsigned']
                );
            }
            $this->audit->record($ncr->company_id, $verifier->id, 'qms.capa_verified', $action);

            return $action->refresh();
        }, 3);
    }

    public function scheduleAudit(array $data, User $actor): InternalAudit
    {
        throw_if($data['auditor_id'] === $data['auditee_id'], ValidationException::withMessages(['auditor' => 'Auditor harus independen dari auditee.']));
        $audit = InternalAudit::create($data);
        $this->audit->record($audit->company_id, $actor->id, 'qms.audit_scheduled', $audit);

        return $audit;
    }

    public function refreshEvidenceStatus(int $companyId): int
    {
        return QmsComplianceMapping::where('company_id', $companyId)->whereNotNull('evidence_expires_at')->whereDate('evidence_expires_at', '<', today())->where('status', '!=', 'evidence_expired')->update(['status' => 'evidence_expired']);
    }
}
