<?php

namespace Tests\Feature\Accounting;

use App\Models\Company;
use App\Models\Customer;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\User;
use App\Services\ReceivablePayableAgingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReceivablePayableAgingTest extends TestCase
{
    use RefreshDatabase;

    public function test_posted_billing_is_bucketed_by_due_date_and_outstanding(): void
    {
        $company = Company::create(['code' => 'GP', 'name' => 'Graha Pondasi']);
        $user = User::factory()->create();
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C-1', 'name' => 'Client 1']);
        $project = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P-1', 'name' => 'Project 1', 'contract_value' => '1000', 'estimated_cost' => '500', 'status' => 'active']);
        ProgressBilling::create(['company_id' => $company->id, 'project_id' => $project->id, 'number' => 'B-1', 'billing_date' => '2026-07-01', 'progress_percent' => '50', 'gross_amount' => '100', 'retention_percent' => '0', 'retention_amount' => '0', 'advance_recovery' => '0', 'net_receivable' => '100', 'status' => 'posted', 'due_date' => '2026-07-15', 'created_by' => $user->id, 'idempotency_key' => 'b-1']);

        $report = app(ReceivablePayableAgingService::class)->generate($company->id, Carbon::parse('2026-08-21'));

        $this->assertSame('100.00', $report['ar_total']);
        $this->assertSame('100.00', $report['rows']->first()['outstanding']);
        $this->assertSame('31–60 hari', $report['rows']->first()['bucket']);
    }
}
