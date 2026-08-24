<?php

namespace Tests\Feature\Workspace;

use App\Models\Company;
use App\Models\CompanyExperience;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Adaptive Workspace Navigation (ADR-077): sidebar menampilkan workspace
 * accordion — aktif mengikuti route, child kontekstual, bukan daftar 50 menu.
 * Sidebar display BUKAN authorization: direct URL tetap divalidasi backend.
 */
class AdaptiveSidebarTest extends TestCase
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
        $role = Role::firstOrCreate(['company_id' => $this->company->id, 'code' => 'adapt-'.md5(implode(',', $codes))], ['name' => 'Test Role']);
        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => str($code)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membership = DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', $this->user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);
    }

    private function sidebarHtml(string $url = '/dashboard'): string
    {
        return $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get($url)->assertOk()->getContent();
    }

    public function test_resolver_matches_group_items_and_children_recursively(): void
    {
        $group = ['key' => 'keuangan', 'label' => 'Keuangan', 'items' => collect([
            ['label' => 'Overview', 'href' => '/admin/finance/overview'],
            ['label' => 'Accounting', 'href' => '/admin/finance', 'children' => [
                ['label' => 'Journals', 'href' => '/admin/finance/journals'],
            ]],
        ])];

        $this->assertTrue(Navigation::isGroupActive($group, '/admin/finance/journals'));
        $this->assertTrue(Navigation::isGroupActive($group, '/admin/finance/overview?tab=x#frag'));
        $this->assertFalse(Navigation::isGroupActive($group, '/admin/documents'));
        // Prefix harus di batas segmen: /admin/finance-x tidak boleh match /admin/finance.
        $this->assertFalse(Navigation::isPathActive('/admin/finance', '/admin/finance-overview'));

        $active = Navigation::activeItems($group, '/admin/finance/journals');
        // Hanya descendant terdalam yang menyala — parent tidak ikut aktif.
        $this->assertSame('/admin/finance/journals', $active->sole()['item']['href']);
    }

    public function test_user_with_project_view_sees_proyek_workspace(): void
    {
        $this->givePermissions(['project.view']);
        $html = $this->sidebarHtml();
        $this->assertStringContainsString('Daftar Proyek &amp; Gantt', $html);
        $this->assertStringContainsString('data-ws-key="proyek"', $html);
    }

    public function test_user_without_project_view_does_not_see_proyek(): void
    {
        $this->givePermissions([]);
        $html = $this->sidebarHtml();
        $this->assertStringNotContainsString('Daftar Proyek', $html);
    }

    public function test_current_route_expands_its_workspace_and_collapses_others(): void
    {
        $this->givePermissions(['project.view', 'document.view']);
        $html = $this->sidebarHtml('/admin/projects');

        // Proyek expanded (server-rendered open), Dokumen & Approval tetap collapsed.
        $this->assertMatchesRegularExpression('/<details class="ws-group ws-active" data-ws-key="proyek"[^>]*\bopen\b[^>]*>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<details class="ws-group[^"]*" data-ws-key="documents-approval"[^>]*\bopen\b[^>]*>/', $html);

        $htmlFieldOps = $this->get('/admin/projects/field-ops')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<details class="ws-group ws-active" data-ws-key="proyek"[^>]*\bopen\b[^>]*>/', $htmlFieldOps);
    }

    public function test_documents_route_expands_documents_workspace(): void
    {
        $this->givePermissions(['document.view']);
        $html = $this->sidebarHtml('/admin/documents');
        $this->assertMatchesRegularExpression('/<details class="ws-group ws-active" data-ws-key="documents-approval"[^>]*\bopen\b[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/class="ws-link active"[^>]*>[^<]*Document Control/', $html);
    }

    public function test_active_child_gets_state_and_renders_under_parent_feature(): void
    {
        $this->givePermissions(['finance.view', 'inventory.view']);
        $html = $this->sidebarHtml('/admin/finance/journals');

        $this->assertMatchesRegularExpression('/class="ws-sublink active"[^>]*>[^<]*Jurnal/', $html);
        // Group lain collapsed saat Keuangan aktif.
        $this->assertDoesNotMatchRegularExpression('/<details class="ws-group[^"]*" data-ws-key="supply-chain"[^>]*open>/', $html);
    }

    public function test_navigation_composer_hidden_group_does_not_appear(): void
    {
        $this->givePermissions(['tender.view']);
        CompanyExperience::create(['company_id' => $this->company->id, 'nav_config' => ['hidden' => [1]], 'published_at' => now()]);

        $html = $this->sidebarHtml();
        $this->assertStringNotContainsString('Komersial', $html);
    }

    public function test_edition_hidden_module_does_not_appear(): void
    {
        $this->givePermissions(['manufacturing.view', 'equipment.view', 'project.view']);
        CompanyExperience::create(['company_id' => $this->company->id, 'edition' => 'equipment-edition', 'published_at' => now()]);

        $html = $this->sidebarHtml();
        // /admin/manufacturing termasuk modul manufacturing — disembunyikan edition.
        $this->assertStringNotContainsString('Manufacturing Control', $html);
        // /admin/projects modul other — tetap tampil.
        $this->assertStringContainsString('Daftar Proyek', $html);
    }

    public function test_company_terminology_rename_applies_to_sidebar_items(): void
    {
        $this->givePermissions(['tender.view']);
        CompanyExperience::create(['company_id' => $this->company->id, 'terminology' => ['Tender & Pelanggan' => 'Lelang & Klien'], 'published_at' => now()]);

        $html = $this->sidebarHtml();
        $this->assertStringContainsString('Lelang &amp; Klien', $html);
    }

    public function test_favorites_still_render_in_sidebar(): void
    {
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
        $this->postJson('/admin/preferences/favorites', ['label' => 'Foundation Control', 'href' => '/admin/projects'])->assertJson(['favorited' => true]);

        $html = $this->sidebarHtml();
        $this->assertStringContainsString('Favorit', $html);
        $this->assertStringContainsString('Foundation Control', $html);
    }

    public function test_company_context_switch_does_not_leak_navigation(): void
    {
        $other = Company::create(['code' => 'XX', 'name' => 'Perusahaan Lain']);
        $this->user->companies()->attach($other, ['is_default' => false, 'is_active' => true]);

        // Permission hanya ada di company GP.
        $this->givePermissions(['project.view']);

        $gp = $this->sidebarHtml('/dashboard');
        $this->assertStringContainsString('Daftar Proyek', $gp);

        // Session pindah ke XX: navigasi GP tidak bocor.
        $xx = $this->withSession(['company_id' => $other->id])->get('/dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString('Daftar Proyek', $xx);
    }

    public function test_direct_url_permission_remains_enforced_by_backend(): void
    {
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
        $this->get('/admin/projects')->assertForbidden();

        $this->givePermissions(['project.view']);
        $this->get('/admin/projects')->assertOk();
    }

    public function test_every_effective_navigation_capability_is_reachable_from_sidebar(): void
    {
        // Menu completeness: semua capability hasil filter effective navigation
        // wajib muncul di output sidebar (collapsed pun tetap di DOM). Child
        // kontekstual wajib ikut ter-render saat halamannya dikunjungi —
        // progressive disclosure tanpa capability yang hilang diam-diam.
        $allCodes = collect(config('modules.nav'))
            ->flatMap(fn (array $group) => collect($group['items'])->map(fn (array $item) => $item['permission'] ?? null))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $this->givePermissions($allCodes);

        $html = $this->sidebarHtml('/dashboard');
        $contextualChildren = [];
        foreach (Navigation::groups($this->user->refresh(), $this->company->id) as $group) {
            foreach ($group['items'] as $item) {
                $this->assertStringContainsString('href="'.e($item['href']).'"', $html, "Capability '{$item['label']}' hilang dari sidebar.");
                foreach ((array) ($item['children'] ?? []) as $child) {
                    if (str_contains((string) $child['href'], '#')) {
                        continue; // anchor in-page milik halaman parent-nya sendiri
                    }
                    $contextualChildren[strtok($child['href'], '?')] = $child;
                }
            }
        }

        // Setiap child non-anchor harus tampil sebagai ws-sublink di halamannya.
        foreach ($contextualChildren as $path => $child) {
            $childHtml = $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('>'.e($child['label']).'</a>', $childHtml, "Child '{$child['label']}' tidak ter-render kontekstual di {$path}.");
        }
    }
}
