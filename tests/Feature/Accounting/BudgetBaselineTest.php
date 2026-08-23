<?php

namespace Tests\Feature\Accounting;

use App\Models\BudgetBaseline;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Services\BudgetBaselineService;
use App\Services\ProjectCostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BudgetBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_versioning_approve_and_supersede_flow(): void
    {
        [$company, $user, $project, $service] = $this->fixture();
        $v1 = $service->createVersion($project, $service->parseLines("MAT-BETON|Beton fc25|38|1500000\nMAT-BAJA|Besi D16|5000|15000"), 'baseline awal', $user);
        $this->assertSame(1, $v1->version);
        $this->assertSame('132000000.00', (string) $v1->total_budget);

        $v1 = $service->approve($v1, $user);
        $this->assertSame('approved', $v1->status);
        $this->assertSame('132000000.00', (string) app(ProjectCostingService::class)->summary($project)['budget']);

        // v2 draft; approve v2 mem-supersede v1.
        $v2 = $service->createVersion($project, $service->parseLines('MAT-BETON|Beton fc25|40|1550000'), 'scope bertambah', $user);
        $this->assertSame(2, $v2->version);
        $this->assertSame('approved', $v1->refresh()->status, 'Approved lama tetap approved sampai v2 disetujui.');
        $service->approve($v2, $user);
        $this->assertSame('superseded', $v1->refresh()->status);
        $this->assertSame('62000000.00', (string) app(ProjectCostingService::class)->summary($project->refresh())['budget']);
    }

    public function test_parse_lines_rejects_bad_rows(): void
    {
        [$company, $user, , $service] = $this->fixture();
        try {
            $service->parseLines('baris-salah-tanpa-pipe');
            $this->fail('Baris tanpa 4 kolom harus ditolak.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        try {
            $service->parseLines('A|Tanpa qty valid|0|100');
            $this->fail('Qty nol harus ditolak.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        $this->assertSame(0, BudgetBaseline::count());
    }

    public function test_without_baseline_falls_back_to_estimated_cost(): void
    {
        [$company, $user, $project] = $this->fixture();
        $summary = app(ProjectCostingService::class)->summary($project);
        $this->assertNull($summary['baseline_version']);
        $this->assertSame((string) ($project->estimated_cost ?? '0'), (string) $summary['budget']);
    }

    private function fixture(string $code = 'GP'): array
    {
        $company = Company::create(['code' => $code, 'name' => $code]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C-'.$code, 'name' => 'Pelanggan']);
        $project = Project::create([
            'company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P-'.$code,
            'name' => 'Proyek '.$code, 'contract_value' => '200000000', 'estimated_cost' => '160000000',
            'planned_start' => now()->subDays(30)->toDateString(), 'planned_end' => now()->addDays(90)->toDateString(), 'status' => 'in_progress',
        ]);
        $service = app(BudgetBaselineService::class);

        return [$company, $user, $project, $service];
    }
}
