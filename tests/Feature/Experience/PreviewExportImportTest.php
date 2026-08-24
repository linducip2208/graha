<?php

namespace Tests\Feature\Experience;

use App\Models\Company;
use App\Models\CompanyExperience;
use App\Models\ExperienceVersion;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PreviewExportImportTest extends TestCase
{
    use RefreshDatabase;

    private function setupManager(): array
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => 'fin-p'], ['name' => 'Fin P']);
        $p = Permission::firstOrCreate(['code' => 'finance.manage'], ['name' => 'finance.manage', 'module' => 'finance']);
        $role->permissions()->syncWithoutDetaching([$p->id]);
        $m = DB::table('company_user')->where('company_id', $company->id)->where('user_id', $user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $m->id, 'role_id' => $role->id]);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return [$company, $user];
    }

    public function test_preview_session_changes_tokens_without_publishing(): void
    {
        [$company, $user] = $this->setupManager();
        $this->post('/admin/experience', ['admin_theme' => 'modern-indigo'])->assertRedirect();
        $this->post('/admin/experience/draft');
        $draft = ExperienceVersion::where('company_id', $company->id)->where('status', 'draft')->first();
        $draft->update(['config' => ['admin_theme' => 'industrial-amber', 'primary_color' => '#B45309']]);

        // Aktifkan pratinjau draft: dashboard memakai token draft.
        $this->post("/admin/experience/versions/{$draft->id}/preview")->assertRedirect();
        $page = $this->get('/dashboard')->assertOk();
        $content = $page->getContent();
        $this->assertStringContainsString('--brand-primary:#B45309', $content);
        $this->assertStringContainsString('MODE PRATINJAU v1', $content);
        // Published belum berubah.
        $row = CompanyExperience::where('company_id', $company->id)->first();
        $this->assertNull($row?->primary_color ?? null);

        // Matikan pratinjau → kembali ke default.
        $this->post('/admin/experience/preview/stop')->assertRedirect();
        $after = $this->get('/dashboard')->getContent();
        $this->assertStringNotContainsString('#B45309', $after);
        $this->assertStringNotContainsString('MODE PRATINJAU', $after);
    }

    public function test_export_import_roundtrip_creates_draft_only(): void
    {
        [$company, $user] = $this->setupManager();
        $this->post('/admin/experience', [
            'admin_theme' => 'corporate-teal', 'primary_color' => '#0E7490',
            'font_ui' => 'Inter', 'system_name' => 'Teal Export Co',
        ])->assertRedirect();

        $export = $this->get('/admin/experience/export');
        $export->assertOk();
        $json = json_decode($export->getContent(), true);
        $this->assertSame('graha-experience@1', $json['schema']);
        $this->assertSame('Teal Export Co', $json['branding']['system_name']);

        // Import file valid → draft baru, published tidak otomatis berganti tema.
        $import = UploadedFile::fake()->createWithContent('theme.json', (string) $export->getContent());
        $before = ExperienceVersion::count() ?? 0;
        $this->from('/admin/experience')->post('/admin/experience/import', ['file' => $import])->assertRedirect();
        $draft = ExperienceVersion::where('company_id', $company->id)->where('status', 'draft')->latest('version')->first();
        $this->assertNotNull($draft, 'Import harus menghasilkan draft.');
        $this->assertSame('corporate-teal', $draft->config['admin_theme']);

        // Schema asing ditolak.
        $bad = UploadedFile::fake()->createWithContent('x.json', '{"schema":"other@9"}');
        $this->from('/admin/experience')->post('/admin/experience/import', ['file' => $bad])->assertSessionHasErrors('file');
    }
}
