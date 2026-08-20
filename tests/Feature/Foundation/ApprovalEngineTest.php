<?php

namespace Tests\Feature\Foundation;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use App\Models\User;
use App\Services\ApprovalEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApprovalEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_is_idempotent_and_self_approval_is_blocked(): void
    {
        $c = Company::create(['code' => 'GP', 'name' => 'GP']);
        $u = User::factory()->create();
        $c->users()->attach($u, ['is_default' => true, 'is_active' => true]);
        $w = ApprovalWorkflow::create(['company_id' => $c->id, 'name' => 'Test', 'document_type' => 'company', 'is_active' => true]);
        ApprovalStep::create(['approval_workflow_id' => $w->id, 'sequence' => 1]);
        $e = app(ApprovalEngine::class);
        $a = $e->submit($w, $c, $u, 'same');
        $b = $e->submit($w, $c, $u, 'same');
        $this->assertSame($a->id, $b->id);
        $this->expectException(ValidationException::class);
        $e->approve($a, $u);
    }
}
