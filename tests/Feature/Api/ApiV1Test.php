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
