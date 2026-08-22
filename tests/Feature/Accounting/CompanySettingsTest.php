<?php

namespace Tests\Feature\Accounting;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Services\ProgressBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_apply_when_fields_omitted(): void
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $user = User::factory()->create();
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C', 'name' => 'Client']);
        $project = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P', 'name' => 'Project', 'contract_value' => '100000', 'status' => 'active']);

        CompanySetting::put($company->id, ['default_retention_percent' => '10', 'default_payment_term_days' => '45']);

        $billing = app(ProgressBillingService::class)->create($project, [
            'number' => 'PB-DEF', 'billing_date' => '2026-08-21', 'progress_percent' => '10',
            'gross_amount' => '1000', 'advance_recovery' => '0', 'idempotency_key' => 'pb-def',
        ], $user);

        $this->assertSame('100.00', $billing->retention_amount);
        $this->assertSame('2026-10-05', $billing->due_date->toDateString());
        $this->assertSame('900.00', $billing->net_receivable);
    }

    public function test_val_falls_back_to_builtin_default(): void
    {
        $company = Company::create(['code' => 'GX', 'name' => 'GX']);
        $this->assertSame('11', CompanySetting::val($company->id, 'default_ppn_percent'));
        $this->assertSame('', CompanySetting::val($company->id, 'unknown_key'));
    }
}
