<?php

namespace Tests\Feature\Project;

use App\Models\AuditLog;
use App\Models\BoredPile;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectZone;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Project Control Center (portfolio index + tabbed workspace).
 * Prinsip: TANPA perubahan business logic — hanya IA/UX. Semua endpoint
 * zone/pile tetap dipakai, forms pindah dari index ke Project Detail.
 */
class ProjectControlCenterTest extends TestCase
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
        $this->givePermissions(['project.view', 'audit.view']);
    }

    private function givePermissions(array $codes): void
    {
        $role = Role::firstOrCreate(['company_id' => $this->company->id, 'code' => 'pcc-'.md5(implode(',', $codes))], ['name' => 'PCC Role']);
        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => str($code)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membership = DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', $this->user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);
    }

    private function makeProject(array $attributes = []): Project
    {
        $customer = Customer::create(['company_id' => $this->company->id, 'code' => 'C-'.uniqid(), 'name' => 'Pelanggan PCC']);

        return Project::create(['company_id' => $this->company->id, 'customer_id' => $customer->id, ...$attributes]);
    }

    public function test_index_is_portfolio_without_inline_operational_forms(): void
    {
        $this->makeProject(['code' => 'PRJ-P1', 'name' => 'Proyek Portfolio', 'status' => 'active']);

        $html = $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get('/admin/projects')->assertOk()->getContent();

        // Portfolio: header + KPI + toolbar tunggal.
        $this->assertStringContainsString('Total Project', $html);
        $this->assertStringContainsString('Nilai Kontrak', $html);
        $this->assertStringContainsString('Rata-rata Progres', $html);
        // Form operasional TIDAK lagi permanen di index.
        $this->assertStringNotContainsString('Tambah Zona', $html);
        $this->assertStringNotContainsString('Tambah Titik Bored Pile', $html);
        // Tabel seluruh pile lintas proyek tidak lagi di index.
        $this->assertStringNotContainsString('Overbreak', $html);
        // View switcher tiga mode.
        $this->assertStringContainsString('>Portfolio</a>', $html);
        $this->assertStringContainsString('>Kanban</a>', $html);
        $this->assertStringContainsString('>Timeline</a>', $html);
    }

    public function test_timeline_view_renders_existing_gantt_contextually(): void
    {
        $this->makeProject(['code' => 'PRJ-TL', 'name' => 'Proyek Timeline', 'status' => 'active']);

        $html = $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get('/admin/projects?view=timeline')->assertOk()->getContent();

        $this->assertStringContainsString('Gantt Titik Pile', $html);
        // Timeline TIDAK memuat tabel portofolio (konteks berbeda).
        $this->assertStringNotContainsString('Nilai Kontrak</th>', $html);
    }

    public function test_kanban_view_still_works(): void
    {
        $this->makeProject(['code' => 'PRJ-KB2', 'name' => 'Kanban PCC', 'status' => 'in_progress']);

        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get('/admin/projects?view=kanban')->assertOk()->assertSee('Berjalan')->assertSee('Kanban PCC');
    }

    public function test_customer_and_health_filters(): void
    {
        $alpha = $this->makeProject(['code' => 'PRJ-CA', 'name' => 'Client Alpha', 'status' => 'active']);
        $other = Customer::create(['company_id' => $this->company->id, 'code' => 'C-B', 'name' => 'Klien Beta']);
        Project::create(['company_id' => $this->company->id, 'customer_id' => $other->id, 'code' => 'PRJ-CB', 'name' => 'Client Beta', 'status' => 'active']);

        // Filter klien: hanya proyek klien pertama yang tampil.
        $html = $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get('/admin/projects?customer='.$alpha->customer_id)->assertOk()->getContent();
        $this->assertStringContainsString('PRJ-CA', $html);
        $this->assertStringNotContainsString('PRJ-CB', $html);

        // Filter health: tanpa billing, proyek aktif berjalan jatuh ke health tertentu —
        // pastikan filter green/yellow/red tidak error dan tetap merender tabel.
        $this->get('/admin/projects?health=green')->assertOk();
        $this->get('/admin/projects?health=red')->assertOk();
    }

    public function test_piles_tab_has_moved_create_forms_and_table(): void
    {
        $project = $this->makeProject(['code' => 'PRJ-PF', 'name' => 'Pile Forms', 'status' => 'active']);
        $zone = ProjectZone::create(['project_id' => $project->id, 'code' => 'ZN-A', 'name' => 'Zona Utara']);

        $html = $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get("/admin/projects/{$project->id}?tab=piles")->assertOk()->getContent();

        // Forms dipindah ke sini sebagai drawer.
        $this->assertStringContainsString('data-drawer-open="zone-create-drawer"', $html);
        $this->assertStringContainsString('data-drawer-open="pile-create-drawer"', $html);
        $this->assertStringContainsString('action="/admin/project-zones"', $html);
        $this->assertStringContainsString('action="/admin/bored-piles"', $html);
        // Tabel pile per proyek tetap ada.
        $this->assertStringContainsString('Genealogi', $html);
    }

    public function test_zone_and_pile_endpoints_still_work_from_detail(): void
    {
        $project = $this->makeProject(['code' => 'PRJ-ZP', 'name' => 'Zona Pile', 'status' => 'active']);
        $this->givePermissions(['project.manage']);
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);

        $this->post('/admin/project-zones', ['project_id' => $project->id, 'code' => 'ZN-1', 'name' => 'Zona Satu'])
            ->assertRedirect()->assertSessionHas('status');
        $zone = ProjectZone::where('project_id', $project->id)->where('code', 'ZN-1')->firstOrFail();

        $this->post('/admin/bored-piles', ['project_id' => $project->id, 'project_zone_id' => $zone->id, 'pile_number' => 'BP-01', 'diameter_mm' => '800', 'planned_depth_m' => '24'])
            ->assertRedirect();
        $this->assertSame(1, BoredPile::where('project_id', $project->id)->count());
    }

    public function test_foundation_tab_renders_summary_and_control_tower_link(): void
    {
        $project = $this->makeProject(['code' => 'PRJ-FD', 'name' => 'Foundation Tab', 'status' => 'active']);
        $zone = ProjectZone::create(['project_id' => $project->id, 'code' => 'ZN-F', 'name' => 'Zona F']);
        BoredPile::create(['project_id' => $project->id, 'project_zone_id' => $zone->id, 'pile_number' => 'BP-F1', 'diameter_mm' => '700', 'planned_depth_m' => '18', 'status' => 'completed', 'created_by' => $this->user->id]);

        $html = $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get("/admin/projects/{$project->id}?tab=foundation")->assertOk()->getContent();

        $this->assertStringContainsString('Foundation Control Tower', $html);
        $this->assertStringContainsString('Overbreak Terlampaui', $html);
        $this->assertStringContainsString('Passport', $html);
    }

    public function test_activity_tab_lists_real_audit_trail(): void
    {
        $project = $this->makeProject(['code' => 'PRJ-AC', 'name' => 'Activity Tab', 'status' => 'active']);
        AuditLog::create(['company_id' => $this->company->id, 'actor_id' => $this->user->id, 'event' => 'project.viewed', 'auditable_type' => Project::class, 'auditable_id' => $project->id, 'entry_hash' => str_repeat('a', 64), 'created_at' => now()]);

        $html = $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get("/admin/projects/{$project->id}?tab=activity")->assertOk()->getContent();

        $this->assertStringContainsString('project.viewed', $html);
    }

    public function test_quality_and_hse_tabs_merged_with_alias(): void
    {
        $project = $this->makeProject(['code' => 'PRJ-QH', 'name' => 'Quality HSE', 'status' => 'active']);
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);

        // Tab quality & alias hse keduanya render (tanpa data permission → section kosong aman).
        $this->get("/admin/projects/{$project->id}?tab=quality")->assertOk();
        $this->get("/admin/projects/{$project->id}?tab=hse")->assertOk();
    }

    public function test_all_project_routes_still_registered(): void
    {
        // Snapshot route: tidak ada endpoint yang hilang setelah redesign.
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($route) => $route->methods()[0].' '.$route->uri());
        foreach ([
            'GET admin/projects', 'GET admin/projects/{project}', 'POST admin/project-zones', 'POST admin/bored-piles',
            'GET admin/projects/field-ops', 'GET admin/projects/{project}/foundation-control',
            'GET admin/bored-piles/{pile}/passport', 'GET admin/bored-piles/{pile}/genealogy',
            'GET admin/bored-piles/{pile}/as-built', 'POST admin/projects/{project}/wbs',
            'POST admin/projects/{project}/constraints', 'POST admin/projects/{project}/procurement-plans',
            'POST admin/bored-piles/{pile}/transition', 'POST admin/bored-piles/{pile}/concrete',
            'GET admin/projects/{project}/piles-as-built', 'POST admin/projects/{project}/handover-package',
        ] as $needle) {
            $this->assertTrue($routes->contains($needle), "Route hilang: {$needle}");
        }
    }
}
