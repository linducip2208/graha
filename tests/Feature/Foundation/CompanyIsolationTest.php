<?php

namespace Tests\Feature\Foundation;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_membership_is_enforced(): void
    {
        $u = User::factory()->create();
        $a = Company::create(['code' => 'A', 'name' => 'A']);
        $b = Company::create(['code' => 'B', 'name' => 'B']);
        $u->companies()->attach($a, ['is_default' => true, 'is_active' => true]);
        $this->actingAs($u)->withSession(['company_id' => $a->id])->get('/dashboard')->assertOk();
        $this->withSession(['company_id' => $b->id])->get('/dashboard')->assertForbidden();
    }
}
