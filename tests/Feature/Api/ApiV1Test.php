<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\NumberSequence;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectDailyReport;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    public function test_token_login_and_company_scoped_project_list(): void
    {
        [$companyA, $user] = $this->fixture('AA');
        [$companyB] = $this->fixture('BB');
        $token = $this->getToken($user);

        $response = $this->getJson('/api/v1/projects', ['Authorization' => "Bearer {$token}", 'X-Company-Id' => $companyA->id]);
        $response->assertOk()->assertJsonCount(1, 'data.data');
        $this->assertSame($companyA->id, $response->json('data.data.0.company_id'));

        $foreign = $this->getJson('/api/v1/projects', ['Authorization' => "Bearer {$token}", 'X-Company-Id' => $companyB->id]);
        $foreign->assertStatus(403);
    }

    public function test_daily_report_creation_requires_permission_and_validates(): void
    {
        [$company, $user] = $this->fixture('AC');
        Permission::firstOrCreate(['code' => 'project.manage'], ['name' => 'Project Manage', 'module' => 'project']);
        $role = Role::create(['company_id' => $company->id, 'code' => 'pm-api', 'name' => 'PM API']);
        $role->permissions()->attach(Permission::where('code', 'project.manage')->first()->id);
        $pivot = DB::table('company_user')->where(['company_id' => $company->id, 'user_id' => $user->id])->value('id');
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $pivot, 'role_id' => $role->id]);
        $token = $this->getToken($user);
        $project = Project::first();

        $ok = $this->postJson('/api/v1/daily-reports', [
            'project_id' => $project->id,
            'report_date' => today()->toDateString(),
            'work_summary' => 'Drilling BP-001 selesai 12m',
            'manpower_count' => 12,
        ], ['Authorization' => "Bearer {$token}", 'X-Company-Id' => (string) $company->id]);
        $ok->assertCreated();
        $this->assertSame(1, ProjectDailyReport::count());

        $bad = $this->postJson('/api/v1/daily-reports', [
            'project_id' => $project->id,
            'report_date' => today()->toDateString(),
        ], ['Authorization' => "Bearer {$token}", 'X-Company-Id' => (string) $company->id]);
        $bad->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_constraints_and_procurement_plans_endpoints_are_scoped(): void
    {
        [$companyA, $user] = $this->fixture('AD');
        [$companyB] = $this->fixture('BE');
        foreach (['project.view', 'procurement.view'] as $code) {
            Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => str($code)->before('.')]);
        }
        $role = Role::create(['company_id' => $companyA->id, 'code' => 'viewer-api', 'name' => 'Viewer API']);
        $role->permissions()->syncWithoutDetaching(Permission::whereIn('code', ['project.view', 'procurement.view'])->pluck('id'));
        $pivot = DB::table('company_user')->where(['company_id' => $companyA->id, 'user_id' => $user->id])->value('id');
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $pivot, 'role_id' => $role->id]);

        $projectA = Project::where('company_id', $companyA->id)->first();
        $foreignProject = Project::where('company_id', $companyB->id)->first();
        \App\Models\ConstraintLog::create(['company_id' => $companyA->id, 'project_id' => $projectA->id, 'type' => 'permit', 'title' => 'Izin lingkungan terlambat', 'description' => 'Pengurusan izin menunggu dokumen pemilik lahan.', 'status' => 'open', 'raised_at' => now()->toDateString(), 'recorded_by' => $user->id]);
        \App\Models\ProcurementPlan::create(['company_id' => $companyA->id, 'project_id' => $projectA->id, 'title' => 'Semen 500 ton', 'quantity' => '500.0000', 'estimated_value' => '3500000.00', 'required_date' => now()->subDays(5)->toDateString(), 'planned_po_date' => now()->subDays(10)->toDateString(), 'status' => 'planned', 'created_by' => $user->id]);
        $token = $this->getToken($user);

        $ok = $this->getJson('/api/v1/constraints?project_id='.$projectA->id, ['Authorization' => "Bearer {$token}", 'X-Company-Id' => (string) $companyA->id]);
        $ok->assertOk()->assertJsonCount(1, 'data');

        // Proyek perusahaan lain -> 404 meski token valid.
        $this->getJson('/api/v1/constraints?project_id='.$foreignProject->id, ['Authorization' => "Bearer {$token}", 'X-Company-Id' => (string) $companyA->id])->assertNotFound();

        $plans = $this->getJson('/api/v1/procurement-plans?project_id='.$projectA->id, ['Authorization' => "Bearer {$token}", 'X-Company-Id' => (string) $companyA->id]);
        $plans->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_late', true);

        // Token tanpa permission apapun -> 403 (authorization backend).
        $outsider = User::factory()->create();
        $outsider->companies()->attach($companyA->id, ['is_default' => true, 'is_active' => true]);
        // RequestGuard sanctum ter-cache per container lintas request dalam satu
        // test; reset agar token outsider diselesaikan sebagai user-nya sendiri.
        $this->app->make('auth')->forgetGuards();
        $tokenOut = $this->getToken($outsider);
        $resp = $this->getJson('/api/v1/constraints?project_id='.$projectA->id, ['Authorization' => 'Bearer '.$tokenOut, 'X-Company-Id' => (string) $companyA->id]);
        $resp->assertStatus(403);
    }

    private function fixture(string $code): array
    {
        $company = Company::create(['code' => $code, 'name' => $code]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        FiscalPeriod::create(['company_id' => $company->id, 'name' => 'FY2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        NumberSequence::create(['company_id' => $company->id, 'document_type' => 'journal', 'prefix' => 'JV', 'padding' => 5, 'last_reset_year' => 2026]);
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C-'.$code, 'name' => 'Client']);
        Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P-'.$code, 'name' => 'Proyek '.$code, 'status' => 'in_progress']);

        return [$company, $user];
    }

    private function getToken(User $user): string
    {
        $response = $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'password', 'device' => 'test']);

        return $response->json('token');
    }
}
