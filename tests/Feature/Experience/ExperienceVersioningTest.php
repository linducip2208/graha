<?php

namespace Tests\Feature\Experience;

use App\Models\Company;
use App\Models\CompanyExperience;
use App\Models\ExperienceVersion;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExperienceVersioningTest extends TestCase
{
    use RefreshDatabase;

    private array $ctx;

    private function setupCompany(): void
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => 'fin-v'], ['name' => 'Fin V']);
        $p = Permission::firstOrCreate(['code' => 'finance.manage'], ['name' => 'finance.manage', 'module' => 'finance']);
        $role->permissions()->syncWithoutDetaching([$p->id]);
        $m = DB::table('company_user')->where('company_id', $company->id)->where('user_id', $user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $m->id, 'role_id' => $role->id]);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);
        $this->ctx = [$company, $user];
    }

    public function test_draft_publish_archives_previous_and_rollback_restores(): void
    {
        $this->setupCompany();
        [$company, $user] = $this->ctx;

        // v1: aktifkan indigo lewat studio, simpan draft, publish.
        $this->post('/admin/experience', ['admin_theme' => 'modern-indigo'])->assertRedirect();
        $this->post('/admin/experience/draft');
        $v1 = ExperienceVersion::where('company_id', $company->id)->orderByDesc('version')->first();
        $this->post("/admin/experience/versions/{$v1->id}/publish")->assertRedirect();

        // v2: draft baru, ubah config ke amber, publish → v1 ter-arsip.
        $this->post('/admin/experience/draft');
        $v2 = ExperienceVersion::where('company_id', $company->id)->where('status', 'draft')->first();
        $v2->update(['config' => ['admin_theme' => 'industrial-amber', 'primary_color' => '#B45309']]);
        $this->post("/admin/experience/versions/{$v2->id}/publish")->assertRedirect();
        $this->assertSame('#B45309', app(ThemeService::class)->resolve($company->id)['tokens']['--brand-primary']);
        $this->assertSame('archived', $v1->refresh()->status);

        // Rollback ke v1: jadi versi baru published, indigo kembali.
        $this->post("/admin/experience/versions/{$v1->id}/rollback")->assertRedirect();
        $resolvedAfter = app(ThemeService::class)->resolve($company->id);
        $this->assertSame('#4f46e5', $resolvedAfter['tokens']['--brand-primary']);
        $publishedCount = ExperienceVersion::where('company_id', $company->id)->where('status', 'published')->count();
        $this->assertSame(1, $publishedCount, 'Rollback menghasilkan tepat satu published baru.');
    }

    public function test_logo_upload_private_storage_and_svg_sanitized(): void
    {
        $this->setupCompany();
        [$company, $user] = $this->ctx;
        Storage::fake('local');

        // SVG berbahaya disanitasi.
        $dirtySvg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect onclick="x()" width="10" height="10"/></svg>';
        $this->from('/admin/experience')->post('/admin/experience/assets', [
            'kind' => 'logo', 'file' => UploadedFile::fake()->createWithContent('logo.svg', $dirtySvg),
        ])->assertRedirect();

        $row = CompanyExperience::where('company_id', $company->id)->firstOrFail();
        $content = Storage::disk('local')->get($row->logo_path);
        $this->assertFalse(stripos($content, '<script') !== false, 'Script di SVG harus dibuang.');
        $this->assertFalse(stripos($content, 'onclick') !== false);

        // Non-gambar ditolak.
        $this->from('/admin/experience')->post('/admin/experience/assets', [
            'kind' => 'favicon', 'file' => UploadedFile::fake()->create('evil.exe', 50),
        ])->assertSessionHasErrors('file');

        // Serving: guest punya akses ke file terdaftar; file asing 404.
        $logoUrl = '/branding/'.$company->id.'/'.basename($row->logo_path);
        $this->get($logoUrl)->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->get('/branding/'.$company->id.'/tidak-ada.png')->assertNotFound();

        // Layout memakai system_name & favicon dari branding.
        $this->actingAs($user)->withSession(['company_id' => $company->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('/branding/', false)
            ->assertSee('favicon-default.svg', false);
    }
}
