<?php

namespace Tests\Feature\Public;

use App\Models\Company;
use App\Models\CompanyExperience;
use App\Models\Document;
use App\Models\DocumentSignature;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Public Frontend V3: homepage, docs, login gating, verifikasi tanda tangan.
 */
class PublicFrontendTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_full_structure_with_real_assets(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Struktur wajib: hero, alur kerja, modul, keamanan, CTA akhir.
        $this->assertStringContainsString('ERP Konstruksi', $html);
        $this->assertStringContainsString('id="flow"', $html);
        $this->assertStringContainsString('id="modules"', $html);
        $this->assertStringContainsString('id="foundation"', $html);
        $this->assertStringContainsString('id="security"', $html);
        $this->assertStringContainsString('Dari peluang sampai serah terima', $html);
        // Screenshot produk asli (bukan placeholder).
        $this->assertStringContainsString('marketing/screens/dashboard-redesign-v2-1440.png', $html);
        $this->assertStringContainsString('images/apps/project.webp', $html);
        // Navbar publik + footer.
        $this->assertStringContainsString('aria-label="Navigasi publik"', $html);
        $this->assertStringContainsString('Masuk', $html);
    }

    public function test_homepage_uses_company_public_site_overrides(): void
    {
        CompanyExperience::create([
            'company_id' => Company::create(['code' => 'GP', 'name' => 'GP'])->id,
            'system_name' => 'PT Pondasi Nusantara',
            'public_site' => [
                'enabled' => true,
                'hero_title' => 'Hero Kustom Perusahaan',
                'sections' => ['flow' => false],
            ],
            'published_at' => now(),
        ]);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Hero Kustom Perusahaan', $html);
        $this->assertStringContainsString('PT Pondasi Nusantara', $html);
        // Section yang dimatikan tidak dirender.
        $this->assertStringNotContainsString('Dari peluang sampai serah terima', $html);
    }

    public function test_docs_renders_documentation_center_with_toc(): void
    {
        $html = $this->get('/docs')->assertOk()->getContent();
        $this->assertStringContainsString('Documentation Center', $html);
        $this->assertStringContainsString('Quick Start', $html);
        $this->assertStringContainsString('Foundation Control Tower', $html);
    }

    public function test_login_demo_credentials_hidden_outside_local(): void
    {
        config(['app.show_demo_credentials' => false]);
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertStringNotContainsString('admin@grahapondasi.test', $html);

        config(['app.show_demo_credentials' => true]);
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString('admin@grahapondasi.test', $html);
    }

    public function test_signature_verification_page_valid_and_invalid(): void
    {
        Storage::fake('local');
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $user = User::factory()->create();
        $document = Document::create(['company_id' => $company->id, 'document_type' => 'contract', 'number' => 'K-V1', 'title' => 'Kontrak Verifikasi', 'owner_id' => $user->id]);
        $contents = '%PDF-1.4 verifikasi';
        $version = $document->versions()->create([
            'version' => 1, 'revision' => '0', 'disk' => 'local', 'path' => 'verify/kontrak.pdf',
            'sha256' => hash('sha256', $contents), 'size_bytes' => strlen($contents), 'mime_type' => 'application/pdf', 'created_by' => $user->id,
        ]);
        Storage::disk('local')->put($version->path, $contents);
        $signature = DocumentSignature::create([
            'company_id' => $company->id, 'document_version_id' => $version->id,
            'signer_id' => $user->id, 'signer_name' => 'Budi Verifikator', 'signer_position' => 'Direktur',
            'signature_type' => 'digital_certificate', 'status' => 'completed',
            'signed_hash' => $version->sha256, 'signed_at' => now(),
        ]);

        // Token valid: format {id}-{hmac24}.
        $expected = substr(hash_hmac('sha256', 'signature-verify:'.$signature->id.':'.$signature->signed_hash, (string) config('app.key')), 0, 24);
        $html = $this->get('/verify/'.$signature->id.'-'.$expected)->assertOk()->getContent();
        $this->assertStringContainsString('Tanda Tangan TERVERIFIKASI', $html);
        $this->assertStringContainsString('Budi Verifikator', $html);
        $this->assertStringContainsString('Kontrak Verifikasi', $html);

        // Token tidak valid -> 404 (bukan halaman debug).
        $this->get('/verify/9999-deadbeefdeadbeefdeadbeef')->assertNotFound();
    }

    public function test_public_site_settings_saved_and_hero_uploaded(): void
    {
        Storage::fake('local');
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $user = User::factory()->create();
        $user->companies()->attach($company, ['is_default' => true, 'is_active' => true]);
        $this->giveFinanceManage($company->id, $user);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $this->post('/admin/experience/public-site', [
            'enabled' => '1',
            'hero_title' => 'Hero Baru',
            'hero_subtitle' => 'Subjudul baru',
            'cta1_label' => 'Coba',
            'cta1_url' => '/login',
            'footer_text' => 'Footer kustom',
            'sections' => ['flow' => '1', 'modules' => '1'],
        ])->assertRedirect()->assertSessionHas('status');

        $site = CompanyExperience::first()->public_site;
        $this->assertSame('Hero Baru', $site['hero_title']);
        $this->assertSame('Footer kustom', $site['footer_text']);
        $this->assertSame(['flow' => '1', 'modules' => '1'], $site['sections']);

        // Upload hero image -> WebP tersimpan privat + path tercatat.
        \imagepng(\imagecreatetruecolor(2000, 1100), $tmp = tempnam(sys_get_temp_dir(), 'hero'));
        $this->post('/admin/experience/public-site/hero', ['file' => new UploadedFile($tmp, 'hero.png', 'image/png', null, true)])
            ->assertRedirect();
        $site = CompanyExperience::first()->public_site;
        $this->assertStringContainsString('public/hero-', (string) ($site['hero_image'] ?? ''));
        Storage::disk('local')->assertExists($site['hero_image']);

        // Tanpa permission -> 403.
        $other = User::factory()->create();
        $other->companies()->attach($company, ['is_default' => false, 'is_active' => true]);
        $this->actingAs($other)->withSession(['company_id' => $company->id])
            ->post('/admin/experience/public-site', ['enabled' => '1'])->assertForbidden();
    }

    private function giveFinanceManage(int $companyId, User $user): void
    {
        $permission = Permission::firstOrCreate(['code' => 'finance.manage'], ['name' => 'finance.manage', 'module' => 'finance']);
        $role = Role::firstOrCreate(['company_id' => $companyId, 'code' => 'pub-site'], ['name' => 'Public Site']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $membership = DB::table('company_user')->where('company_id', $companyId)->where('user_id', $user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);
    }
}
