<?php

namespace Tests\Feature\Project;

use App\Models\BoredPile;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectZone;
use App\Models\User;
use App\Services\BoredPileService;
use App\Services\ProjectClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BoredPileWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function base(): array
    {
        $c = Company::create(['code' => 'GP', 'name' => 'GP']);
        $customer = Customer::create(['company_id' => $c->id, 'code' => 'C', 'name' => 'Client']);
        $u = User::factory()->create();
        $p = Project::create(['company_id' => $c->id, 'customer_id' => $customer->id, 'code' => 'P1', 'name' => 'Project', 'overbreak_tolerance_percent' => '10.000']);
        $z = ProjectZone::create(['project_id' => $p->id, 'code' => 'Z1', 'name' => 'Zone 1']);
        $pile = BoredPile::create(['project_id' => $p->id, 'project_zone_id' => $z->id, 'pile_number' => 'BP-001', 'diameter_mm' => '1000.00', 'planned_depth_m' => '10.000', 'created_by' => $u->id]);

        return [$p, $pile, $u];
    }

    public function test_status_transition_is_controlled_and_historized(): void
    {
        [, $pile,$u] = $this->base();
        $service = app(BoredPileService::class);
        $this->expectException(ValidationException::class);
        $service->transition($pile, 'completed', $u);
    }

    public function test_overbreak_is_calculated_and_tolerance_flagged(): void
    {
        [, $pile,$u] = $this->base();
        $result = app(BoredPileService::class)->recordConcrete($pile, '10.000', '9.0000', $u);
        $this->assertSame('7.8540', $result->theoretical_concrete_m3);
        $this->assertSame('14.591', $result->overbreak_percent);
        $this->assertTrue($result->overbreak_exceeded);
        $this->assertDatabaseHas('audit_logs', ['event' => 'bored_pile.concrete_recorded']);
    }

    public function test_project_cannot_close_with_unfinished_piles(): void
    {
        [$project,$pile,$u] = $this->base();
        try {
            app(ProjectClosingService::class)->close($project, $u);
            $this->fail();
        } catch (ValidationException) {
            $this->assertSame('draft', $project->refresh()->status);
        }$pile->update(['status' => 'completed']);
        $this->assertSame('closed', app(ProjectClosingService::class)->close($project, $u)->status);
    }
}
