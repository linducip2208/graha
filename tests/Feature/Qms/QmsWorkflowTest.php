<?php

namespace Tests\Feature\Qms;

use App\Models\Company;
use App\Models\Department;
use App\Models\Nonconformity;
use App\Models\QmsClause;
use App\Models\QmsComplianceMapping;
use App\Models\QmsStandard;
use App\Models\User;
use App\Services\QmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QmsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_risk_score_and_auditor_independence(): void
    {
        $c = Company::create(['code' => 'GP', 'name' => 'GP']);
        $u = User::factory()->create();
        $risk = app(QmsService::class)->createRisk(['company_id' => $c->id, 'code' => 'R-1', 'type' => 'risk', 'title' => 'Cuaca', 'description' => 'Hujan', 'likelihood' => 4, 'impact' => 5, 'owner_id' => $u->id], $u);
        $this->assertSame(20, $risk->inherent_score);
        $d = Department::create(['company_id' => $c->id, 'code' => 'QC', 'name' => 'QC']);
        $this->expectException(ValidationException::class);
        app(QmsService::class)->scheduleAudit(['company_id' => $c->id, 'number' => 'A-1', 'scope' => 'QC', 'criteria' => 'Internal', 'department_id' => $d->id, 'auditor_id' => $u->id, 'auditee_id' => $u->id, 'scheduled_at' => '2026-09-01'], $u);
    }

    public function test_capa_requires_independent_verifier_and_closes_ncr(): void
    {
        $c = Company::create(['code' => 'GP', 'name' => 'GP']);
        $owner = User::factory()->create();
        $verifier = User::factory()->create();
        $ncr = Nonconformity::create(['company_id' => $c->id, 'number' => 'NCR-1', 'source_type' => 'inspection', 'severity' => 'minor', 'description' => 'Slump', 'reported_by' => $owner->id]);
        $action = $ncr->actions()->create(['action' => 'Perbaiki prosedur', 'owner_id' => $owner->id, 'due_at' => '2026-09-01', 'evidence' => 'EV-1']);
        try {
            app(QmsService::class)->verifyCapa($action, $owner, 'ok');
            $this->fail();
        } catch (ValidationException) {
            $this->assertSame('open', $action->refresh()->status);
        }$verified = app(QmsService::class)->verifyCapa($action, $verifier, 'Efektif');
        $this->assertSame('effective', $verified->status);
        $this->assertSame('closed', $ncr->refresh()->status);
    }

    public function test_expired_evidence_is_flagged(): void
    {
        $c = Company::create(['code' => 'GP', 'name' => 'GP']);
        $s = QmsStandard::create(['code' => 'ISO-TEST', 'name' => 'Baseline Internal', 'edition' => '2026']);
        $clause = QmsClause::create(['qms_standard_id' => $s->id, 'code' => '1', 'title' => 'Test', 'internal_requirement_summary' => 'Ringkasan internal']);
        QmsComplianceMapping::create(['company_id' => $c->id, 'qms_clause_id' => $clause->id, 'process_code' => 'PROC-1', 'status' => 'compliant', 'evidence_expires_at' => today()->subDay()]);
        $this->assertSame(1, app(QmsService::class)->refreshEvidenceStatus($c->id));
        $this->assertDatabaseHas('qms_compliance_mappings', ['status' => 'evidence_expired']);
    }
}
