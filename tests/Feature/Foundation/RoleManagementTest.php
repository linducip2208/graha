<?php

namespace Tests\Feature\Foundation;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_role_sync_permissions_and_manage_members(): void
    {
        [$company, $admin, $member] = $this->fixture();
        $managePerm = Permission::firstOrCreate(['code' => 'inventory.view'], ['name' => 'Inventory View', 'module' => 'inventory']);

        $this->actingAs($admin)->withSession(['company_id' => $company->id])
            ->post('/admin/organization/roles', ['code' => 'gudang', 'name' => 'Admin Gudang'])
            ->assertRedirect();
        $role = Role::where('company_id', $company->id)->where('code', 'gudang')->firstOrFail();

        $this->actingAs($admin)->withSession(['company_id' => $company->id])
            ->post("/admin/organization/roles/{$role->id}/permissions", ['permissions' => [(string) $managePerm->id]])
            ->assertRedirect();
        $this->assertSame(1, $role->permissions()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'organization.role_permissions_updated', 'auditable_id' => $role->id]);

        $pivot = DB::table('company_user')->where(['company_id' => $company->id, 'user_id' => $member->id])->value('id');
        $this->actingAs($admin)->withSession(['company_id' => $company->id])
            ->post("/admin/organization/roles/{$role->id}/members", ['user_id' => (string) $member->id])
            ->assertRedirect();
        $this->assertDatabaseHas('company_user_role', ['company_user_id' => $pivot, 'role_id' => $role->id]);

        $page = $this->actingAs($admin)->withSession(['company_id' => $company->id])->get("/admin/organization/roles?role={$role->id}");
        $page->assertOk()->assertSee($member->name);

        $this->actingAs($admin)->withSession(['company_id' => $company->id])
            ->post("/admin/organization/roles/{$role->id}/members/{$member->id}/detach")
            ->assertRedirect();
        $this->assertDatabaseMissing('company_user_role', ['company_user_id' => $pivot, 'role_id' => $role->id]);
    }

    public function test_system_role_permissions_are_locked(): void
    {
        [$company, $admin] = $this->fixture();
        $system = Role::create(['company_id' => $company->id, 'code' => 'sys', 'name' => 'System', 'is_system' => true]);

        $response = $this->actingAs($admin)->withSession(['company_id' => $company->id])
            ->post("/admin/organization/roles/{$system->id}/permissions", []);

        $response->assertStatus(403);
    }

    public function test_user_without_organization_manage_is_forbidden(): void
    {
        [$company, , $plain] = $this->fixture();

        $response = $this->actingAs($plain)->withSession(['company_id' => $company->id])
            ->post('/admin/organization/roles', ['code' => 'x1', 'name' => 'X']);

        $response->assertStatus(403);
    }

    private function fixture(): array
    {
        $company = Company::create(['code' => 'RM'.rand(10, 99), 'name' => 'RM']);
        $admin = User::factory()->create();
        $member = User::factory()->create();
        foreach ([$admin, $member] as $u) {
            $u->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        }
        foreach ([['organization.view'], ['organization.manage']] as [$code]) {
            $perm = Permission::firstOrCreate(['code' => $code], ['name' => str($code)->replace('.', ' ')->title(), 'module' => 'organization']);
            $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => 'mgr-'.$code], ['name' => $code]);
            $role->permissions()->syncWithoutDetaching([$perm->id]);
            $pivotId = DB::table('company_user')->where(['company_id' => $company->id, 'user_id' => $admin->id])->value('id');
            DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $pivotId, 'role_id' => $role->id]);
        }

        return [$company, $admin, $member];
    }
}
