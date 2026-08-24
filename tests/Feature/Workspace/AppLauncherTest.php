<?php

namespace Tests\Feature\Workspace;

use App\Models\Company;
use App\Models\CompanyExperience;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserFavorite;
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
}
