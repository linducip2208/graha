<?php

namespace Tests\Feature\Foundation;

use App\Models\ApprovalDelegation;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdvancedApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function membership(Company $company, User $user, ?Role $role = null): void
    {
        $company->users()->attach($user, ['is_default' => true, 'is_active' => true]);
        if ($role) {
            $membership = DB::table('company_user')->where(['company_id' => $company->id, 'user_id' => $user->id])->first();
            DB::table('company_user_role')->insert(['company_user_id' => $membership->id, 'role_id' => $role->id]);
        }
    }

    public function test_quorum_requires_configured_number_of_approvers(): void
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $role = Role::create(['company_id' => $company->id, 'code' => 'director', 'name' => 'Director']);
        $submitter = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->membership($company, $submitter);
        $this->membership($company, $first, $role);
        $this->membership($company, $second, $role);
        $workflow = ApprovalWorkflow::create(['company_id' => $company->id, 'name' => 'Quorum', 'document_type' => 'company', 'is_active' => true]);
        ApprovalStep::create(['approval_workflow_id' => $workflow->id, 'sequence' => 1, 'role_id' => $role->id, 'mode' => 'quorum', 'quorum' => 2, 'sla_hours' => 24]);
        $request = app(ApprovalEngine::class)->submit($workflow, $company, $submitter, 'quorum');
        $this->assertNotNull($request->due_at);
        $this->assertSame('pending', app(ApprovalEngine::class)->approve($request, $first)->status);
        $this->assertSame('approved', app(ApprovalEngine::class)->approve($request, $second)->status);
    }

    public function test_approved_temporary_delegate_can_decide(): void
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $role = Role::create(['company_id' => $company->id, 'code' => 'manager', 'name' => 'Manager']);
        $submitter = User::factory()->create();
        $manager = User::factory()->create();
        $delegate = User::factory()->create();
        $approver = User::factory()->create();
        $this->membership($company, $submitter);
        $this->membership($company, $manager, $role);
        $this->membership($company, $delegate);
        $this->membership($company, $approver);
        ApprovalDelegation::create(['company_id' => $company->id, 'delegator_id' => $manager->id, 'delegate_id' => $delegate->id, 'role_id' => $role->id, 'starts_at' => now()->subHour(), 'ends_at' => now()->addDay(), 'reason' => 'Cuti', 'approved_by' => $approver->id]);
        $workflow = ApprovalWorkflow::create(['company_id' => $company->id, 'name' => 'Delegasi', 'document_type' => 'company', 'is_active' => true]);
        ApprovalStep::create(['approval_workflow_id' => $workflow->id, 'sequence' => 1, 'role_id' => $role->id, 'mode' => 'any']);
        $request = app(ApprovalEngine::class)->submit($workflow, $company, $submitter, 'delegated');
        $this->assertSame('approved', app(ApprovalEngine::class)->approve($request, $delegate)->status);
    }
}
