<?php

namespace Tests\Feature\Workspace;

use App\Models\Company;
use App\Models\CompanyExperience;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserFavorite;
use App\Support\AppLauncher;
use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppLauncherTest extends TestCase
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

    private function actingAsMember(): self
    {
        return $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
    }

    private function givePermissions(array $codes): void
    {
        $role = Role::firstOrCreate(['company_id' => $this->company->id, 'code' => 'launcher-'.md5(implode(',', $codes))], ['name' => 'Launcher Role']);
        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => str($code)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membershipId = DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', $this->user->id)->value('id');
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membershipId, 'role_id' => $role->id]);
    }

    public function test_apps_requires_login(): void
    {
        $this->get('/apps')->assertRedirect('/login');
    }

    public function test_launcher_shows_permitted_workspaces_and_hides_forbidden(): void
    {
        // Tanpa permission apa pun: hanya grup tanpa permission requirement (Beranda/Pengaturan).
        $response = $this->actingAsMember()->get('/apps');
        $response->assertOk();
        $response->assertSee('data-view-pane', false);
        $response->assertDontSee('Tender, pelanggan, kontrak dan administrasi perubahan.');

        $this->givePermissions(['tender.view']);
        $this->actingAsMember()->get('/apps')
            ->assertOk()
            ->assertSee('Tender, pelanggan, kontrak dan administrasi perubahan.')
            ->assertSee('/admin/tenders', false);
    }

    public function test_edition_hidden_module_hides_workspace_card(): void
    {
        // equipment-edition hanya menyertakan modul 'other' -> manufaktur tersembunyi.
        CompanyExperience::updateOrCreate(['company_id' => $this->company->id], ['edition' => 'equipment-edition']);
        $this->givePermissions(['manufacturing.view']);

        $visibleModules = Edition::visibleModules($this->company->id);
        if ($visibleModules !== null && ! $visibleModules->contains('manufacturing')) {
            $this->actingAsMember()->get('/apps')
                ->assertOk()
                ->assertDontSee('Manufaktur, cage & casing, equipment, BBM dan maintenance.')
                ->assertSee('Dashboard eksekutif, pekerjaan saya, dan ringkasan aktivitas.');
        } else {
            $this->fail('Edition equipment-edition seharusnya menyembunyikan manufacturing.');
        }
    }

    public function test_navigation_composer_hidden_workspace_and_rename_apply_to_launcher(): void
    {
        $this->givePermissions(['tender.view']);
        $totalGroups = count(config('modules.nav'));
        $hiddenIdx = 1; // komersial
        CompanyExperience::updateOrCreate(['company_id' => $this->company->id], [
            'nav_config' => ['hidden' => [$hiddenIdx], 'labels' => [(string) 2 => 'Pekerjaan & Lapangan']],
        ]);

        $this->actingAsMember()->get('/apps')
            ->assertOk()
            ->assertDontSee('Tender, pelanggan, kontrak dan administrasi perubahan.');
    }

    public function test_default_covers_exist_for_all_workspace_keys(): void
    {
        foreach (config('modules.nav') as $group) {
            $key = $group['key'] ?? null;
            $this->assertNotNull($key, "Grup {$group['label']} belum punya key stabil.");
            $meta = config("app-launcher.workspaces.{$key}");
            $this->assertNotNull($meta, "Registry app-launcher tidak punya entry untuk {$key}.");
            $this->assertFileExists(public_path($meta['cover']), "Cover default hilang: {$meta['cover']}");
        }
    }

    public function test_custom_cover_upload_validates_rejects_bad_files_and_scopes_per_company(): void
    {
        Storage::fake('local');
        $this->givePermissions(['finance.manage']);
        CompanyExperience::updateOrCreate(['company_id' => $this->company->id]);

        // MIME salah ditolak.
        $this->actingAsMember()
            ->post('/admin/experience/launcher/covers', ['workspace_key' => 'proyek', 'file' => UploadedFile::fake()->createWithContent('evil.svg', '<svg><script>alert(1)</script></svg>')])
            ->assertSessionHasErrors('file');

        // Oversize ditolak (6 MB > 5 MB).
        $this->actingAsMember()
            ->post('/admin/experience/launcher/covers', ['workspace_key' => 'proyek', 'file' => UploadedFile::fake()->create('big.png', 6 * 1024 * 1024, 'image/png')])
            ->assertSessionHasErrors('file');

        // Upload valid PNG -> dioptimalkan menjadi WebP 1200x675 di disk privat.
        \imagepng(\imagecreatetruecolor(2400, 1400), $tmp = tempnam(sys_get_temp_dir(), 'cov'));
        $this->actingAsMember()
            ->post('/admin/experience/launcher/covers', ['workspace_key' => 'proyek', 'file' => new UploadedFile($tmp, 'cover.png', 'image/png', null, true)])
            ->assertRedirect();

        $covers = CompanyExperience::find($this->company->id)?->launcher_covers ?? [];
        $path = $covers['proyek'] ?? null;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);
        $info = getimagesizefromstring(Storage::disk('local')->get($path));
        $this->assertSame(1200, $info[0]);
        $this->assertSame(675, $info[1]);

        // URL cover company lain -> 404 (tidak bocor lintas company).
        $other = Company::create(['code' => 'XX2', 'name' => 'Lain']);
        $basename = basename($path);
        $this->get('/branding/'.$other->id.'/'.$basename)->assertNotFound();
        $this->get('/branding/'.$this->company->id.'/'.$basename)->assertOk();

        // Delete mengembalikan default.
        $this->actingAsMember()->post('/admin/experience/launcher/covers/delete', ['workspace_key' => 'proyek'])->assertRedirect();
        $this->assertNull((CompanyExperience::find($this->company->id)?->launcher_covers ?? [])['proyek'] ?? null);
    }

    public function test_favorites_backend_still_works_with_launcher_cards(): void
    {
        $this->actingAsMember();
        $csrf = $this->get('/apps')->assertOk();
        $this->actingAsMember()->postJson('/admin/preferences/favorites', ['label' => 'Proyek', 'href' => '/admin/projects'])
            ->assertOk()->assertJson(['favorited' => true]);
        $this->assertSame(1, UserFavorite::where('user_id', $this->user->id)->count());
        $this->actingAsMember()->get('/apps')->assertOk()->assertSee('Proyek');

        $this->postJson('/admin/preferences/favorites', ['label' => 'Proyek', 'href' => '/admin/projects'])
            ->assertOk()->assertJson(['favorited' => false]);
        $this->assertSame(0, UserFavorite::count());
    }

    public function test_recents_section_renders_recorded_views(): void
    {
        $this->actingAsMember()->post('/admin/preferences/recent', ['label' => 'Document Control', 'href' => '/admin/documents'])->assertNoContent();

        $this->actingAsMember()->get('/apps')
            ->assertOk()
            ->assertSee('Terakhir Dibuka')
            ->assertSee('Document Control');
    }

    public function test_visual_grid_is_three_columns_on_desktop(): void
    {
        $html = $this->actingAsMember()->get('/apps')->assertOk()->getContent();
        // Grid visual: 1 kolom mobile, 2 tablet, 3 desktop.
        $this->assertStringContainsString('md:grid-cols-2', $html);
        $this->assertStringContainsString('xl:grid-cols-3', $html);
    }

    public function test_missing_cover_file_falls_back_to_gradient_without_error(): void
    {
        // Registry menunjuk file yang tidak ada -> card tetap render (gradient + icon),
        // <img> punya onerror fallback sehingga tidak broken image.
        config(['app-launcher.workspaces.proyek.cover' => 'images/apps/tidak-ada.webp']);
        $this->givePermissions(['project.view']);

        $html = $this->actingAsMember()->get('/apps')->assertOk()->getContent();
        $this->assertStringContainsString('images/apps/tidak-ada.webp', $html);
        $this->assertStringContainsString('onerror="this.remove()"', $html);
        $this->assertStringContainsString('Planning, WBS, field operations', $html);
    }

    public function test_terminology_rename_reflected_on_launcher_card(): void
    {
        $this->givePermissions(['tender.view']);
        CompanyExperience::updateOrCreate(['company_id' => $this->company->id], [
            'terminology' => ['Tender & Pelanggan' => 'Lelang & Klien'],
        ]);

        $html = $this->actingAsMember()->get('/apps')->assertOk()->getContent();
        $this->assertStringContainsString('Lelang &amp; Klien', $html);
    }

    public function test_covers_enabled_false_hides_cover_images_but_keeps_cards(): void
    {
        $this->givePermissions(['project.view']);
        CompanyExperience::updateOrCreate(['company_id' => $this->company->id], ['launcher_config' => ['style' => 'visual', 'covers_enabled' => false, 'density' => 'comfortable']]);

        $html = $this->actingAsMember()->get('/apps')->assertOk()->getContent();
        $this->assertStringNotContainsString('images/apps/project.webp', $html);
        $this->assertStringContainsString('Planning, WBS, field operations', $html, 'Card tetap render dengan gradient+icon saat cover OFF.');
    }

    public function test_launcher_style_list_is_persistable_and_used_as_default_view(): void
    {
        $this->givePermissions(['finance.manage']);
        $this->actingAsMember()->post('/admin/experience/launcher', ['style' => 'list', 'covers_enabled' => '1', 'density' => 'comfortable'])
            ->assertRedirect()->assertSessionHas('status');

        $html = $this->actingAsMember()->get('/apps')->assertOk()->getContent();
        $this->assertStringContainsString('data-view-default="list"', $html);
    }

    public function test_cover_upload_requires_experience_permission(): void
    {
        $this->givePermissions(['project.view']); // tanpa finance.manage
        $this->actingAsMember()
            ->post('/admin/experience/launcher/covers', ['workspace_key' => 'proyek', 'file' => UploadedFile::fake()->createWithContent('c.png', "\x89PNG\r\n")])
            ->assertForbidden();
    }

    public function test_cover_manager_lists_only_effective_workspaces(): void
    {
        // User punya akses studio (finance.manage) tapi TIDAK punya tender.view —
        // workspace Komersial tidak boleh muncul sebagai editable cover card.
        $this->givePermissions(['finance.manage', 'project.view']);
        $html = $this->actingAsMember()->get('/admin/experience')->assertOk()->getContent();

        $this->assertStringContainsString('data-cover-key="proyek"', $html);
        $this->assertStringNotContainsString('data-cover-key="komersial"', $html);
        // Default registry cover dipakai sebagai preview untuk workspace tanpa custom.
        $this->assertStringContainsString('images/apps/project.webp', $html);
    }

    public function test_custom_cover_overrides_default_in_launcher_and_studio_preview(): void
    {
        Storage::fake('local');
        $this->givePermissions(['finance.manage', 'project.view']);
        CompanyExperience::updateOrCreate(['company_id' => $this->company->id]);
        \imagepng(\imagecreatetruecolor(1600, 900), $tmp = tempnam(sys_get_temp_dir(), 'cov2'));
        $this->actingAsMember()
            ->post('/admin/experience/launcher/covers', ['workspace_key' => 'proyek', 'file' => new UploadedFile($tmp, 'custom.png', 'image/png', null, true)])
            ->assertRedirect();

        // Level support: cover proyek kini URL branding custom, bukan registry default.
        $workspaces = collect(AppLauncher::workspaces($this->user, $this->company->id));
        $proyek = $workspaces->firstWhere('key', 'proyek');
        $this->assertNotNull($proyek);
        $this->assertStringStartsWith('/branding/'.$this->company->id.'/', (string) $proyek['cover']);

        // Level render: URL branding benar-benar muncul di /apps.
        $this->actingAsMember()->get('/apps')->assertOk()->assertSee('/branding/'.$this->company->id.'/', false);

        // Reset: kembali ke default registry.
        $this->actingAsMember()->post('/admin/experience/launcher/covers/delete', ['workspace_key' => 'proyek'])->assertRedirect();
        $after = collect(AppLauncher::workspaces($this->user, $this->company->id))->firstWhere('key', 'proyek');
        $this->assertSame('images/apps/project.webp', $after['cover']);
    }
}
