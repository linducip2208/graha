<?php

namespace Tests\Feature\Foundation;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Organization Center V3: KPI + drawer create (endpoint tetap).
 * Regression: create cabang/departemen + isolasi company + permission.
 */
class OrganizationCenterTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['code' => 'GP', 'name' => 'Graha Pondasi']);
        $this->user = User::factory()->create();
        $this->user->companies()->attach($this->company, ['is_default' => true, 'is_active' => true]);
    }

    private function givePermissions(array $codes): void
    {
        $role = Role::firstOrCreate(['company_id' => $this->company->id, 'code' => 'orgc-'.md5(implode(',', $codes))], ['name' => 'OrgC Role']);
        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => str($code)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membership = DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', $this->user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);
    }

    public function test_organization_center_renders_kpi_and_lists(): void
    {
        $this->givePermissions(['organization.view', 'organization.manage']);
        Branch::create(['company_id' => $this->company->id, 'code' => 'KLT-01', 'name' => 'Cabang Surabaya', 'is_active' => true]);
        Department::create(['company_id' => $this->company->id, 'code' => 'ENG', 'name' => 'Engineering']);

        $html = $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get('/admin/organization')->assertOk()->getContent();

        $this->assertStringContainsString('Total Cabang', $html);
        $this->assertStringContainsString('Total Departemen', $html);
        $this->assertStringContainsString('Member Aktif', $html);
        $this->assertStringContainsString('Cabang Surabaya', $html);
        // Create via drawer, bukan form permanen.
        $this->assertStringContainsString('data-drawer-open="branch-create-drawer"', $html);
        $this->assertStringContainsString('data-drawer-open="department-create-drawer"', $html);
        $this->assertStringContainsString('action="/admin/branches"', $html);
        $this->assertStringContainsString('action="/admin/departments"', $html);
    }

    public function test_create_branch_and_department_via_existing_endpoints(): void
    {
        $this->givePermissions(['organization.view', 'organization.manage']);
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);

        $this->post('/admin/branches', ['code' => 'KLT-02', 'name' => 'Cabang Medan'])->assertRedirect();
        $branch = Branch::where('company_id', $this->company->id)->where('code', 'KLT-02')->firstOrFail();

        $this->post('/admin/departments', ['code' => 'QMS', 'name' => 'Quality Department', 'branch_id' => $branch->id])->assertRedirect();
        $this->assertSame($branch->id, Department::where('company_id', $this->company->id)->where('code', 'QMS')->firstOrFail()->branch_id);
    }

    public function test_create_requires_organization_manage_permission(): void
    {
        $this->givePermissions(['organization.view']);
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post('/admin/branches', ['code' => 'X', 'name' => 'Tanpa Izin'])
            ->assertForbidden();
        $this->assertSame(0, DB::table('branches')->count());
    }

    public function test_branch_is_company_scoped(): void
    {
        $other = Company::create(['code' => 'YY', 'name' => 'Lain']);
        Branch::create(['company_id' => $other->id, 'code' => 'KLT-XX', 'name' => 'Cabang Perusahaan Lain']);

        $this->givePermissions(['organization.view']);
        $html = $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get('/admin/organization')->assertOk()->getContent();
        $this->assertStringNotContainsString('Cabang Perusahaan Lain', $html);
    }
}
