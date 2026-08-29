<?php

namespace Tests\Feature\Security;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectUserAccess;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Support\AccessScopeService;
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

    public function test_missing_branch_or_department_scope_fails_closed(): void
    {
        $company = Company::create(['code' => 'SCOPE2', 'name' => 'Scope Company 2']);
        $user = User::factory()->create();
        $user->companies()->attach($company, ['is_default' => true, 'is_active' => true]);
        $membership = DB::table('company_user')->where(['company_id' => $company->id, 'user_id' => $user->id])->first();
        $service = app(AccessScopeService::class);

        DB::table('company_user')->where('id', $membership->id)->update(['data_scope' => 'branch', 'scope_branch_id' => null]);
        $this->assertSame([], $service->accessibleProjectIds($user, $company->id));

        $branch = Branch::create(['company_id' => $company->id, 'code' => 'B1', 'name' => 'Branch 1']);
        DB::table('company_user')->where('id', $membership->id)->update(['data_scope' => 'department', 'scope_branch_id' => $branch->id, 'scope_department_id' => null]);
        $this->assertSame([], $service->accessibleProjectIds($user, $company->id));

        $foreign = Company::create(['code' => 'FOREIGN', 'name' => 'Foreign']);
        $foreignBranch = Branch::create(['company_id' => $foreign->id, 'code' => 'B1', 'name' => 'Foreign Branch']);
        DB::table('company_user')->where('id', $membership->id)->update(['data_scope' => 'branch', 'scope_branch_id' => $foreignBranch->id]);
        $this->assertSame([], $service->accessibleProjectIds($user, $company->id));
    }

    public function test_child_scope_handles_purchase_orders_through_purchase_request_without_sql_leak(): void
    {
        $company = Company::create(['code' => 'SCOPE3', 'name' => 'Scope Company 3']);
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'CUST3', 'name' => 'Customer']);
        $project = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P3', 'name' => 'P3', 'status' => 'active']);
        $user = User::factory()->create();
        $user->companies()->attach($company, ['is_default' => true, 'is_active' => true]);
        $membership = DB::table('company_user')->where(['company_id' => $company->id, 'user_id' => $user->id])->first();
        DB::table('company_user')->where('id', $membership->id)->update(['data_scope' => 'projects']);
        ProjectUserAccess::create(['company_id' => $company->id, 'project_id' => $project->id, 'user_id' => $user->id, 'access_level' => 'view']);
        $request = PurchaseRequest::create(['company_id' => $company->id, 'project_id' => $project->id, 'number' => 'PR-3', 'status' => 'draft', 'requested_by' => $user->id]);
        $vendor = Vendor::create(['company_id' => $company->id, 'code' => 'V3', 'name' => 'Vendor 3']);
        PurchaseOrder::create(['company_id' => $company->id, 'purchase_request_id' => $request->id, 'vendor_id' => $vendor->id, 'created_by' => $user->id, 'number' => 'PO-3', 'status' => 'draft', 'order_date' => today()]);

        $this->assertSame(1, app(AccessScopeService::class)->applyToChildQuery(PurchaseOrder::query(), $user, $company->id)->count());
    }
}
