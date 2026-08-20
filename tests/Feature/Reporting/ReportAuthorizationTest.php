<?php

namespace Tests\Feature\Reporting;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_require_backend_permission_and_export_is_separate(): void
    {
        $company = Company::create(['code' => 'GP', 'name' => 'Graha Pondasi']);
        $user = User::factory()->create();
        $user->companies()->attach($company, ['is_default' => true, 'is_active' => true]);

        $this->actingAs($user)->withSession(['company_id' => $company->id])->get('/admin/reports/executive')->assertForbidden();
        $role = Role::create(['company_id' => $company->id, 'code' => 'reporter', 'name' => 'Reporter']);
        $view = Permission::create(['code' => 'report.view', 'name' => 'View reports', 'module' => 'report']);
        $role->permissions()->attach($view);
        $membership = DB::table('company_user')->where('company_id', $company->id)->where('user_id', $user->id)->first();
        DB::table('company_user_role')->insert(['company_user_id' => $membership->id, 'role_id' => $role->id]);

        $this->actingAs($user)->withSession(['company_id' => $company->id])->get('/admin/reports/executive')->assertOk()->assertSee('Laporan Bisnis');
        $this->get('/admin/reports/executive/export')->assertForbidden();

        $export = Permission::create(['code' => 'report.export', 'name' => 'Export reports', 'module' => 'report']);
        $role->permissions()->attach($export);
        $this->get('/admin/reports/executive/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
