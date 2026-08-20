<?php

namespace Tests\Feature\Hse;

use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use App\Models\Customer;
use App\Models\HseIncident;
use App\Models\JobSafetyAnalysis;
use App\Models\Nonconformity;
use App\Models\Project;
use App\Models\RiskOpportunity;
use App\Models\User;
use App\Services\HseService;
use App\Services\ManagementReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_permit_requires_approved_jsa_and_must_stay_within_validity(): void
    {
        [$company, $project, $owner] = $this->fixture();
        $jsa = JobSafetyAnalysis::create(['company_id' => $company->id, 'project_id' => $project->id, 'number' => 'JSA-1', 'activity' => 'Lifting cage', 'hazards' => ['beban jatuh'], 'controls' => ['lifting plan'], 'risk_level' => 'high', 'valid_from' => '2026-08-01', 'valid_until' => '2026-08-31', 'prepared_by' => $owner->id]);
        try {
            app(HseService::class)->issuePermit($jsa, ['number' => 'PTW-1', 'permit_type' => 'lifting', 'work_location' => 'Zone A', 'valid_from' => '2026-08-21 08:00:00', 'valid_until' => '2026-08-21 17:00:00'], $owner);
            $this->fail('Permit tanpa approval harus ditolak.');
        } catch (ValidationException) {
            $this->assertSame('draft', $jsa->refresh()->status);
        }
        $workflow = ApprovalWorkflow::create(['company_id' => $company->id, 'name' => 'JSA', 'document_type' => 'jsa']);
        ApprovalRequest::create(['company_id' => $company->id, 'approval_workflow_id' => $workflow->id, 'approvable_type' => JobSafetyAnalysis::class, 'approvable_id' => $jsa->id, 'submitted_by' => $owner->id, 'status' => 'approved', 'idempotency_key' => 'jsa-approval', 'submitted_at' => now(), 'completed_at' => now()]);
        app(HseService::class)->activateApprovedJsa($jsa, $owner);
        $permit = app(HseService::class)->issuePermit($jsa->refresh(), ['number' => 'PTW-1', 'permit_type' => 'lifting', 'work_location' => 'Zone A', 'valid_from' => '2026-08-21 08:00:00', 'valid_until' => '2026-08-21 17:00:00'], $owner);
        $this->assertSame('issued', $permit->status);
    }

    public function test_incident_needs_root_cause_and_independently_verified_actions_before_close(): void
    {
        [$company, $project, $owner] = $this->fixture();
        $verifier = User::factory()->create();
        $incident = HseIncident::create(['company_id' => $company->id, 'project_id' => $project->id, 'number' => 'INC-1', 'type' => 'near_miss', 'severity' => 'low', 'occurred_at' => now(), 'location' => 'Zone A', 'description' => 'Sling hampir putus', 'root_cause' => 'Inspeksi tidak dilakukan', 'reported_by' => $owner->id]);
        $action = $incident->actions()->create(['action' => 'Pre-use inspection', 'owner_id' => $owner->id, 'due_at' => today()->addDay(), 'evidence' => 'Checklist-01']);
        try {
            app(HseService::class)->verifyAction($action, $owner);
            $this->fail('Self verification harus ditolak.');
        } catch (ValidationException) {
            $this->assertSame('open', $action->refresh()->status);
        }
        app(HseService::class)->verifyAction($action, $verifier);
        $closed = app(HseService::class)->closeIncident($incident, $verifier);
        $this->assertSame('closed', $closed->status);
    }

    public function test_management_review_snapshot_uses_current_company_evidence(): void
    {
        [$company, $project, $owner] = $this->fixture();
        RiskOpportunity::create(['company_id' => $company->id, 'code' => 'R1', 'type' => 'risk', 'title' => 'Cuaca', 'description' => 'Hujan', 'likelihood' => 2, 'impact' => 3, 'inherent_score' => 6, 'owner_id' => $owner->id]);
        Nonconformity::create(['company_id' => $company->id, 'project_id' => $project->id, 'number' => 'NCR-1', 'source_type' => 'inspection', 'severity' => 'minor', 'description' => 'Test', 'reported_by' => $owner->id]);
        $review = app(ManagementReviewService::class)->createSnapshot($company->id, 'MR-1', '2026-08-21', $owner);
        $this->assertSame(1, $review->inputs_snapshot['open_risks']);
        $this->assertSame(1, $review->inputs_snapshot['open_ncr']);
    }

    private function fixture(): array
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $owner = User::factory()->create();
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C', 'name' => 'Client']);
        $project = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P', 'name' => 'Project', 'status' => 'active']);

        return [$company, $project, $owner];
    }
}
