<?php

namespace Tests\Feature\Accounting;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectCostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_forecast_is_idempotent_and_eac_uses_decimal_math(): void
    {
        $company = Company::create(['code' => 'GP', 'name' => 'Graha Pondasi']);
        $user = User::factory()->create();
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C', 'name' => 'Client']);
        $project = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P', 'name' => 'Project', 'contract_value' => '1500.00', 'estimated_cost' => '1000.00', 'status' => 'active']);
        $service = app(ProjectCostingService::class);

        $forecast = $service->forecast($project, ['forecast_date' => '2026-08-21', 'cost_to_complete' => '850.25', 'basis' => 'Forecast lapangan', 'idempotency_key' => 'forecast-1'], $user);
        $duplicate = $service->forecast($project, ['forecast_date' => '2026-08-21', 'cost_to_complete' => '850.25', 'basis' => 'Forecast lapangan', 'idempotency_key' => 'forecast-1'], $user);
        $summary = $service->summary($project);

        $this->assertSame($forecast->id, $duplicate->id);
        $this->assertSame('850.25', $summary['cost_to_complete']);
        $this->assertSame('850.25', $summary['eac']);
        $this->assertSame('149.75', $summary['variance']);
        $this->assertSame('1500.00', $summary['contract_value']);
    }
}
