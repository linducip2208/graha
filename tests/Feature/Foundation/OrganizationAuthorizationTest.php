<?php

namespace Tests\Feature\Foundation;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrganizationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_requires_backend_permission_and_scopes_created_branch(): void
    {
        $company = Company::create(['code' => 'GP', 'name' => 'Graha Pondasi']);
        $user = User::factory()->create();
        $user->companies()->attach($company, ['is_default' => true, 'is_active' => true]);
        $this->actingAs($user)->withSession(['company_id' => $company->id])->get('/admin/organization')->assertForbidden();

        $role = Role::create(['company_id' => $company->id, 'code' => 'admin', 'name' => 'Admin']);
        $permissions = collect(['organization.view', 'organization.manage'])->map(fn ($code) => Permission::create(['code' => $code, 'name' => $code, 'module' => 'organization']));
        $role->permissions()->attach($permissions);
        $membership = DB::table('company_user')->where('user_id', $user->id)->first();
        DB::table('company_user_role')->insert(['company_user_id' => $membership->id, 'role_id' => $role->id]);

        $this->actingAs($user)->withSession(['company_id' => $company->id])->post('/admin/branches', ['code' => 'JKT', 'name' => 'Jakarta'])->assertRedirect();
        $this->assertDatabaseHas('branches', ['company_id' => $company->id, 'code' => 'JKT']);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'event' => 'organization.branch_created']);
    }
}
