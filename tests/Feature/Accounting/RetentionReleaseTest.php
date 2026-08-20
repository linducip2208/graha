<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\NumberSequence;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\RetentionRelease;
use App\Models\User;
use App\Services\RetentionReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RetentionReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_release_is_capped_approved_posted_and_idempotent(): void
    {
        [$company, $user, $project] = $this->fixture();
        $service = app(RetentionReleaseService::class);
        $release = $service->create($project, ['number' => 'RR-1', 'release_date' => '2026-08-21', 'amount' => '75.00', 'idempotency_key' => 'rr-1'], $user);
        $this->assertSame($release->id, $service->create($project, ['number' => 'RR-1', 'release_date' => '2026-08-21', 'amount' => '75.00', 'idempotency_key' => 'rr-1'], $user)->id);

        $workflow = ApprovalWorkflow::create(['company_id' => $company->id, 'name' => 'Retention', 'document_type' => 'retention_release']);
        ApprovalRequest::create(['company_id' => $company->id, 'approval_workflow_id' => $workflow->id, 'approvable_type' => RetentionRelease::class, 'approvable_id' => $release->id, 'submitted_by' => $user->id, 'status' => 'approved', 'idempotency_key' => 'approval-rr', 'submitted_at' => now(), 'completed_at' => now()]);
        $service->activateApproved($release, $user);
        $posted = $service->post($release->refresh(), $user);
        $journal = $posted->journal()->with('entries')->first();
        $this->assertSame('75.00', $journal->entries->reduce(fn ($sum, $entry) => bcadd($sum, $entry->debit, 2), '0'));
        $this->assertSame('75.00', $journal->entries->reduce(fn ($sum, $entry) => bcadd($sum, $entry->credit, 2), '0'));
        $this->assertSame($journal->id, $service->post($posted, $user)->journal_id);

        $this->expectException(ValidationException::class);
        $service->create($project, ['number' => 'RR-2', 'release_date' => '2026-08-21', 'amount' => '30.00', 'idempotency_key' => 'rr-2'], $user);
    }

    private function fixture(): array
    {
        $company = Company::create(['code' => 'GP', 'name' => 'Graha Pondasi']);
        $user = User::factory()->create();
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C', 'name' => 'Client']);
        $project = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P', 'name' => 'Project', 'contract_value' => '1000', 'status' => 'active']);
        ProgressBilling::create(['company_id' => $company->id, 'project_id' => $project->id, 'number' => 'PB', 'billing_date' => '2026-08-01', 'progress_percent' => '100', 'gross_amount' => '1000', 'retention_percent' => '10', 'retention_amount' => '100', 'advance_recovery' => '0', 'net_receivable' => '900', 'status' => 'posted', 'created_by' => $user->id, 'idempotency_key' => 'pb']);
        FiscalPeriod::create(['company_id' => $company->id, 'name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        NumberSequence::create(['company_id' => $company->id, 'document_type' => 'journal', 'prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);
        $ar = Account::create(['company_id' => $company->id, 'code' => 'AR', 'name' => 'AR', 'type' => 'asset', 'normal_balance' => 'debit']);
        $ret = Account::create(['company_id' => $company->id, 'code' => 'RET', 'name' => 'Retention', 'type' => 'asset', 'normal_balance' => 'debit']);
        AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'retention_release', 'entry_side' => 'ar_debit', 'account_id' => $ar->id]);
        AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'retention_release', 'entry_side' => 'retention_credit', 'account_id' => $ret->id]);

        return [$company, $user, $project];
    }
}
