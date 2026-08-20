<?php

namespace Tests\Feature\Reporting;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_does_not_disclose_module_metrics_without_permission(): void
    {
        $company = Company::create(['code' => 'GP', 'name' => 'Graha Pondasi']);
        $user = User::factory()->create();
        $user->companies()->attach($company, ['is_default' => true, 'is_active' => true]);
        $response = $this->actingAs($user)->withSession(['company_id' => $company->id])->get('/dashboard');
        $response->assertOk()->assertDontSee('Tender Aktif')->assertSee('Belum ada widget');

        $role = Role::create(['company_id' => $company->id, 'code' => 'marketing', 'name' => 'Marketing']);
        $permission = Permission::create(['code' => 'tender.view', 'name' => 'View tender', 'module' => 'tender']);
        $role->permissions()->attach($permission);
        $membership = DB::table('company_user')->where('company_id', $company->id)->where('user_id', $user->id)->first();
        DB::table('company_user_role')->insert(['company_user_id' => $membership->id, 'role_id' => $role->id]);

        $this->get('/dashboard')->assertOk()->assertSee('Tender Aktif')->assertDontSee('Item Stok');
    }
}
