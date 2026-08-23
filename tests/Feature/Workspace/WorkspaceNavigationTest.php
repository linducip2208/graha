<?php

namespace Tests\Feature\Workspace;

use App\Models\Company;
use App\Models\ContractChange;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use App\Services\TenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkspaceNavigationTest extends TestCase
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
        $role = Role::firstOrCreate(['company_id' => $this->company->id, 'code' => 'test-role-'.md5(implode(',', $codes))], ['name' => 'Test Role']);
        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => str($code)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membership = DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', $this->user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);
    }

    private function makeProject(array $attributes = []): Project
    {
        $customer = Customer::create(['company_id' => $this->company->id, 'code' => 'C-'.uniqid(), 'name' => 'Pelanggan Test']);

        return Project::create(['company_id' => $this->company->id, 'customer_id' => $customer->id, ...$attributes]);
    }

    public function test_app_launcher_only_shows_permitted_workspaces(): void
    {
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get('/apps')
            ->assertOk()
            ->assertSee('Semua Aplikasi')
            ->assertDontSee('Manufacturing Control');

        $this->givePermissions(['manufacturing.view']);
        $this->get('/apps')->assertOk()->assertSee('Manufacturing Control');
    }

    public function test_direct_url_is_rejected_by_backend_without_permission(): void
    {
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
        $this->get('/admin/manufacturing')->assertForbidden();
        $this->get('/admin/contracts')->assertForbidden();
        $this->givePermissions(['contract.view']);
        $this->get('/admin/contracts')->assertOk();
        $this->get('/admin/manufacturing')->assertForbidden();
    }

    public function test_my_work_is_company_scoped_and_renders_for_any_role(): void
    {
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get('/admin/my-work')
            ->assertOk()
            ->assertSee('Pekerjaan Saya');
    }

    public function test_project_detail_tabs_are_permission_scoped(): void
    {
        $project = $this->makeProject(['code' => 'PRJ-T1', 'name' => 'Tower Test', 'status' => 'active']);
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);

        $this->get("/admin/projects/{$project->id}?tab=cost")->assertForbidden();

        $this->givePermissions(['project.view']);
        $this->get("/admin/projects/{$project->id}")->assertOk()->assertSee('Funnel Bored Pile');
        $this->get("/admin/projects/{$project->id}?tab=cost")->assertOk()->assertDontSee('Realisasi per Cost Code');

        $this->givePermissions(['finance.view']);
        $this->get("/admin/projects/{$project->id}?tab=cost")->assertOk()->assertSee('Cockpit Biaya');
    }

    public function test_global_search_respects_permissions_and_company(): void
    {
        $this->makeProject(['code' => 'PRJ-SRCH', 'name' => 'Proyek Cari Alpha']);
        $other = Company::create(['code' => 'XX', 'name' => 'Perusahaan Lain']);
        $customerOther = Customer::create(['company_id' => $other->id, 'code' => 'C-X1', 'name' => 'Pelanggan Lain']);
        Project::create(['company_id' => $other->id, 'customer_id' => $customerOther->id, 'code' => 'PRJ-SRCH2', 'name' => 'Proyek Cari Beta']);

        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
        $response = $this->getJson('/admin/search?q=Cari')->assertOk();
        $results = collect($response->json('results'));

        $this->assertTrue($results->where('type', 'Proyek')->isEmpty(), 'Tidak boleh melihat proyek tanpa permission project.view.');

        $this->givePermissions(['project.view']);
        $results = collect($this->getJson('/admin/search?q=Proyek+Cari')->json('results'));
        $labels = $results->pluck('label')->implode('|');
        $this->assertStringContainsString('PRJ-SRCH', $labels);
        $this->assertStringNotContainsString('Beta', $labels, 'Hasil lintas perusahaan tidak boleh bocor.');
    }

    public function test_favorites_toggle_and_recent_recording(): void
    {
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);

        $this->postJson('/admin/preferences/favorites', ['label' => 'Dashboard', 'href' => '/dashboard'])->assertJson(['favorited' => true]);
        $this->postJson('/admin/preferences/favorites', ['label' => 'Dashboard', 'href' => '/dashboard'])->assertJson(['favorited' => false]);
        $this->assertSame(0, DB::table('user_favorites')->where('user_id', $this->user->id)->count());

        $this->post('/admin/preferences/recent', ['label' => 'Dashboard', 'href' => '/dashboard'])->assertNoContent();
        $this->assertDatabaseHas('user_recent_views', ['user_id' => $this->user->id, 'href' => '/dashboard']);
    }

    public function test_kanban_views_render_for_projects_approvals_and_qms(): void
    {
        $this->makeProject(['code' => 'PRJ-KB', 'name' => 'Kanban Proyek', 'status' => 'in_progress']);
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);

        $this->givePermissions(['project.view']);
        $this->get('/admin/projects?view=kanban')->assertOk()->assertSee('Berjalan')->assertSee('Kanban Proyek');

        $this->givePermissions(['approval.view']);
        $this->get('/admin/approvals?view=kanban')->assertOk()->assertSee('Pending');

        $this->givePermissions(['qms.view']);
        $this->get('/admin/qms?view=kanban')->assertOk()->assertSee('Terbuka');
    }

    public function test_tender_kanban_and_outcome_tab_render_without_directive_errors(): void
    {
        $customer = Customer::create(['company_id' => $this->company->id, 'code' => 'C-KB', 'name' => 'Pelanggan Kanban']);
        $tender = Tender::create(['company_id' => $this->company->id, 'customer_id' => $customer->id, 'number' => 'T-KB', 'year' => 2026, 'project_name' => 'Tender Kanban', 'status' => 'bidding', 'created_by' => $this->user->id]);
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
        $this->givePermissions(['tender.view']);

        $this->get('/admin/tenders?view=kanban')->assertOk()->assertSee('Bidding')->assertSee('Tender Kanban');

        app(TenderService::class)->recordOutcome($tender, $this->user, 'won', ['announced_at' => '2026-08-23', 'contract_value' => '900000000']);
        $this->get("/admin/tenders/{$tender->id}?tab=outcome")->assertOk()->assertSee('MENANG');
    }

    public function test_project_list_filter_and_search_persist_as_saved_view(): void
    {
        $alpha = $this->makeProject(['code' => 'PRJ-F1', 'name' => 'Filter Alpha', 'status' => 'in_progress']);
        $beta = $this->makeProject(['code' => 'PRJ-F2', 'name' => 'Filter Beta', 'status' => 'closed']);
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
        $this->givePermissions(['project.view']);

        // Filter status hanya menampilkan kartu proyek yang cocok.
        $filtered = $this->get('/admin/projects?status=closed');
        $filtered->assertOk()->assertSee("/admin/projects/{$beta->id}")->assertDontSee("/admin/projects/{$alpha->id}\"");

        // Kata kunci pencarian tersimpan sebagai saved view via URL.
        $search = $this->get('/admin/projects?q=Alpha');
        $search->assertOk()->assertSee("/admin/projects/{$alpha->id}")->assertDontSee("/admin/projects/{$beta->id}\"");

        // Kanban mengikuti filter aktif.
        $kanban = $this->get('/admin/projects?view=kanban&status=in_progress');
        $kanban->assertOk()->assertSee('Berjalan')->assertSee('Filter Alpha');
    }

    public function test_contract_change_requires_approval_and_scopes_to_company(): void
    {
        $project = $this->makeProject(['code' => 'PRJ-C1', 'name' => 'Kontrak Test', 'status' => 'active']);
        $other = Company::create(['code' => 'YY', 'name' => 'Lain Lagi']);

        $this->givePermissions(['contract.view', 'contract.manage']);
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
        $response = $this->post('/admin/contracts', [
            'project_id' => $project->id,
            'number' => 'VO-2026-001',
            'type' => 'variation_order',
            'title' => 'Tambah kedalaman pile',
            'amount' => 150000000,
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('contract_changes', ['company_id' => $this->company->id, 'number' => 'VO-2026-001', 'status' => 'draft']);

        $change = ContractChange::where('number', 'VO-2026-001')->first();
        // Sesi dialihkan ke perusahaan lain: akses harus ditolak middleware tenancy (403) atau controller (404).
        $this->actingAs($this->user)->withSession(['company_id' => $other->id])->get("/admin/contracts/{$change->id}")->assertForbidden();

        $sync = new ContractChange;
        $change->syncApprovalStatus('approve');
        $this->assertSame('approved', $change->fresh()->status);
        $this->assertNotNull($change->fresh()->approved_at);
        unset($sync);
    }
}
