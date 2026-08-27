<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectUserAccess;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectDataScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_project_scope_is_enforced_in_project_pages_and_global_search(): void
    {
        $company = Company::create(['code' => 'SCOPE', 'name' => 'Scope Company']);
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'CUST', 'name' => 'Customer']);
        $allowed = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'VISIBLE-PRJ', 'name' => 'Visible Project', 'status' => 'active']);
        $denied = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'SECRET-PRJ', 'name' => 'Secret Project', 'status' => 'active']);
        $user = User::factory()->create();
        $user->companies()->attach($company, ['is_default' => true, 'is_active' => true]);
        DB::table('company_user')->where(['company_id' => $company->id, 'user_id' => $user->id])->update(['data_scope' => 'projects']);
        ProjectUserAccess::create(['company_id' => $company->id, 'project_id' => $allowed->id, 'user_id' => $user->id, 'access_level' => 'view']);
        $role = Role::create(['company_id' => $company->id, 'code' => 'project-reader', 'name' => 'Project Reader']);
        $permission = Permission::create(['code' => 'project.view', 'name' => 'View project', 'module' => 'project']);
        $role->permissions()->attach($permission);
        $membership = DB::table('company_user')->where(['company_id' => $company->id, 'user_id' => $user->id])->first();
        DB::table('company_user_role')->insert(['company_user_id' => $membership->id, 'role_id' => $role->id]);

        $this->actingAs($user)->withSession(['company_id' => $company->id]);
        $this->get(route('projects.index'))->assertOk()->assertSee('VISIBLE-PRJ')->assertDontSee('SECRET-PRJ');
        $this->get(route('projects.show', $denied))->assertNotFound();
        $this->getJson(route('global-search.query', ['q' => 'SECRET']))->assertOk()->assertJsonMissing(['label' => 'SECRET-PRJ — Secret Project']);
        $this->getJson(route('global-search.query', ['q' => 'VISIBLE']))->assertOk()->assertJsonFragment(['label' => 'VISIBLE-PRJ — Visible Project']);
    }
}
