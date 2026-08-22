<?php

namespace Tests\Feature\Foundation;

use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\NumberSequence;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\User;
use App\Services\ApprovalEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApprovalNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_notifies_step_approvers_but_not_submitter(): void
    {
        [$company, $submitter, $approver] = $this->fixture(withWorkflow: true);

        $billing = ProgressBilling::create(['company_id' => $company->id, 'project_id' => Project::first()->id, 'number' => 'PB-N1', 'billing_date' => '2026-08-21', 'progress_percent' => '10', 'gross_amount' => '1000', 'retention_percent' => '0', 'retention_amount' => '0', 'advance_recovery' => '0', 'tax_amount' => '0', 'net_receivable' => '1000', 'status' => 'draft', 'created_by' => $submitter->id, 'idempotency_key' => 'pb-n1']);

        $engine = app(ApprovalEngine::class);
        $engine->submit(ApprovalWorkflow::where('company_id', $company->id)->where('document_type', 'progress_billing')->firstOrFail(), $billing, $submitter, 'sub-1');
        $engine->submit(ApprovalWorkflow::where('company_id', $company->id)->where('document_type', 'progress_billing')->firstOrFail(), $billing, $submitter, 'sub-1');

        $approverNotifications = DatabaseNotification::where('notifiable_id', $approver->id)->get();
        $this->assertSame(1, $approverNotifications->count());
        $this->assertSame('approval_requested', $approverNotifications[0]->data['event']);
        $this->assertNull(DatabaseNotification::where('notifiable_id', $submitter->id)->first());
    }

    public function test_final_approval_notifies_submitter_and_rejection_carries_comment(): void
    {
        [$company, $submitter, $approver] = $this->fixture(withWorkflow: true);

        $billing = ProgressBilling::create(['company_id' => $company->id, 'project_id' => Project::first()->id, 'number' => 'PB-N2', 'billing_date' => '2026-08-21', 'progress_percent' => '10', 'gross_amount' => '1000', 'retention_percent' => '0', 'retention_amount' => '0', 'advance_recovery' => '0', 'tax_amount' => '0', 'net_receivable' => '1000', 'status' => 'draft', 'created_by' => $submitter->id, 'idempotency_key' => 'pb-n2']);
        $workflow = ApprovalWorkflow::where('company_id', $company->id)->where('document_type', 'progress_billing')->firstOrFail();
        $request = app(ApprovalEngine::class)->submit($workflow, $billing, $submitter, 'sub-2');

        app(ApprovalEngine::class)->approve($request->refresh(), $approver, 'Oke');

        $events = DatabaseNotification::where('notifiable_id', $submitter->id)->pluck('data')->map(fn ($data) => $data['event']);
        $this->assertContains('approval_approved', $events->all());

        $notifications = DatabaseNotification::where('notifiable_type', User::class)->get();
        $this->assertTrue($notifications->count() >= 1);
    }

    public function test_sla_command_notifies_overdue_once_per_request(): void
    {
        [$company, $submitter, $approver] = $this->fixture(withWorkflow: false);

        $billing = ProgressBilling::create(['company_id' => $company->id, 'project_id' => Project::first()->id, 'number' => 'PB-SLA', 'billing_date' => '2026-08-21', 'progress_percent' => '10', 'gross_amount' => '1000', 'retention_percent' => '0', 'retention_amount' => '0', 'advance_recovery' => '0', 'tax_amount' => '0', 'net_receivable' => '1000', 'status' => 'draft', 'created_by' => $submitter->id, 'idempotency_key' => 'pb-sla']);
        $workflow = ApprovalWorkflow::create(['company_id' => $company->id, 'name' => 'SLA WF', 'document_type' => 'progress_billing']);
        ApprovalStep::create(['approval_workflow_id' => $workflow->id, 'sequence' => 1, 'role_id' => DB::table('roles')->where('company_id', $company->id)->value('id'), 'mode' => 'any', 'sla_hours' => 4]);
        ApprovalRequest::create(['company_id' => $company->id, 'approval_workflow_id' => $workflow->id, 'approvable_type' => ProgressBilling::class, 'approvable_id' => $billing->id, 'submitted_by' => $submitter->id, 'status' => 'pending', 'current_sequence' => 1, 'idempotency_key' => 'sla-req', 'submitted_at' => now()->subDay(), 'due_at' => now()->subHour()]);

        $this->artisan('approvals:monitor-sla')->assertSuccessful();
        $this->artisan('approvals:monitor-sla')->assertSuccessful();

        $overdueForApprover = DatabaseNotification::where('notifiable_id', $approver->id)->where('data->event', 'approval_sla_overdue')->count();
        $overdueForSubmitter = DatabaseNotification::where('notifiable_id', $submitter->id)->where('data->event', 'approval_sla_overdue')->count();
        $this->assertSame(1, $overdueForApprover);
        $this->assertSame(1, $overdueForSubmitter);
    }

    private function fixture(bool $withWorkflow): array
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $submitter = User::factory()->create();
        $approver = User::factory()->create();
        foreach ([$submitter, $approver] as $user) {
            $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        }
        FiscalPeriod::create(['company_id' => $company->id, 'name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        NumberSequence::create(['company_id' => $company->id, 'document_type' => 'journal', 'prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);

        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C', 'name' => 'Client']);
        $project = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P', 'name' => 'Project', 'contract_value' => '100000', 'status' => 'active']);
        $roleId = DB::table('roles')->insertGetId(['company_id' => $company->id, 'code' => 'approver-role', 'name' => 'Approver', 'is_system' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_role')->insert(array_map(fn ($userId) => ['company_user_id' => DB::table('company_user')->where('user_id', $userId)->where('company_id', $company->id)->value('id'), 'role_id' => $roleId], [$approver->id]));
        if ($withWorkflow) {
            $workflow = ApprovalWorkflow::create(['company_id' => $company->id, 'name' => 'WF Billing', 'document_type' => 'progress_billing']);
            ApprovalStep::create(['approval_workflow_id' => $workflow->id, 'sequence' => 1, 'role_id' => $roleId, 'mode' => 'any']);
        }

        return [$company, $submitter, $approver];
    }
}
