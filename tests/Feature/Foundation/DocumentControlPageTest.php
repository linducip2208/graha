<?php

namespace Tests\Feature\Foundation;

use App\Models\Company;
use App\Models\Document;
use App\Models\NumberSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Document Control P0 redesign: UI berubah (header → KPI → filter → table →
 * drawer create + record workspace), tetapi perilaku bisnis TIDAK boleh
 * berubah — upload, validasi, download, isolasi perusahaan, pagination.
 */
class DocumentControlPageTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->company = Company::create(['code' => 'GP', 'name' => 'Graha Pondasi']);
        $this->user = User::factory()->create();
        $this->user->companies()->attach($this->company, ['is_default' => true, 'is_active' => true]);
        NumberSequence::create(['company_id' => $this->company->id, 'document_type' => 'generic', 'prefix' => 'GP', 'padding' => 4, 'last_reset_year' => (int) now()->format('Y')]);
    }

    private function givePermissions(array $codes): void
    {
        $role = Role::firstOrCreate(['company_id' => $this->company->id, 'code' => 'doc-'.md5(implode(',', $codes))], ['name' => 'Test Role']);
        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => str($code)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membership = DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', $this->user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);
    }

    public function test_index_renders_enterprise_pattern_without_permanent_form(): void
    {
        $this->givePermissions(['document.view']);
        $html = $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get('/admin/documents')->assertOk()->getContent();

        // Struktur halaman baru.
        $this->assertStringContainsString('Total Dokumen', $html);
        $this->assertStringContainsString('data-drawer-open="document-create-drawer"', $html);
        // Form upload BUKAN bagian default view (ada di drawer tersembunyi).
        $this->assertStringNotContainsString('<form method="post" action="/admin/documents" enctype="multipart/form-data" class="mt-8', $html);
    }

    public function test_upload_still_works_from_drawer_form(): void
    {
        $this->givePermissions(['document.view', 'document.manage']);
        $response = $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post('/admin/documents', [
                'document_type' => 'method_statement',
                'title' => 'Method Statement Bored Pile',
                'file' => UploadedFile::fake()->createWithContent('ms.pdf', '%PDF-1.4 method statement'),
                'change_reason' => 'Versi awal untuk proyek dermaga',
            ]);

        $response->assertRedirect();
        $document = Document::where('company_id', $this->company->id)->where('title', 'Method Statement Bored Pile')->first();
        $this->assertNotNull($document);
        $version = $document->versions()->first();
        $this->assertNotNull($version);
        Storage::disk($version->disk)->assertExists($version->path);
    }

    public function test_validation_still_enforced(): void
    {
        $this->givePermissions(['document.view', 'document.manage']);
        $response = $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->from('/admin/documents')
            ->post('/admin/documents', [
                'document_type' => 'policy',
                'file' => UploadedFile::fake()->createWithContent('x.pdf', '%PDF-1.4'),
                'change_reason' => 'tanpa judul',
            ]);

        $response->assertSessionHasErrors('title');
        $this->assertSame(0, DB::table('documents')->count());
    }

    public function test_download_and_record_workspace_render(): void
    {
        $this->givePermissions(['document.view', 'document.manage']);
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
        $this->post('/admin/documents', [
            'document_type' => 'drawing',
            'title' => 'Gambar Kerja Pile Cap',
            'file' => UploadedFile::fake()->createWithContent('gbr.pdf', '%PDF-1.4 gambar'),
            'change_reason' => 'Rilis awal',
        ]);
        $document = Document::where('title', 'Gambar Kerja Pile Cap')->firstOrFail();

        // Record workspace: overview + versions; activity dari audit trail riil.
        $show = $this->get("/admin/documents/{$document->id}")->assertOk()->getContent();
        $this->assertStringContainsString('Versions', $show);
        $this->assertStringContainsString('Revisi Berlaku', $show);

        // Tab versions menampilkan riwayat revisi + alasan perubahan.
        $versionsTab = $this->get("/admin/documents/{$document->id}?tab=versions")->assertOk()->getContent();
        $this->assertStringContainsString('Rilis awal', $versionsTab);

        // Tab activity dirender karena audit trail benar-benar ada.
        $activityTab = $this->get("/admin/documents/{$document->id}?tab=activity")->assertOk()->getContent();
        $this->assertStringContainsString('document.version_created', $activityTab);

        // Tab approval tidak dirender bila tidak ada data approval (bukan modul palsu).
        $this->assertStringNotContainsString('?tab=approval', $show);

        // Download versi tetap bekerja.
        $version = $document->versions()->firstOrFail();
        $this->get("/admin/document-versions/{$version->id}/download")->assertOk();
    }

    public function test_company_isolation_on_record_and_download(): void
    {
        $other = Company::create(['code' => 'YY', 'name' => 'Lain']);
        $otherUser = User::factory()->create();
        $otherUser->companies()->attach($other, ['is_default' => true, 'is_active' => true]);
        $role = Role::firstOrCreate(['company_id' => $other->id, 'code' => 'doc-other'], ['name' => 'Doc Other']);
        $permission = Permission::firstOrCreate(['code' => 'document.view'], ['name' => 'document.view', 'module' => 'document']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $membership = DB::table('company_user')->where('company_id', $other->id)->where('user_id', $otherUser->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);

        $this->givePermissions(['document.view', 'document.manage']);
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post('/admin/documents', [
                'document_type' => 'contract', 'title' => 'Kontrak Rahasia',
                'file' => UploadedFile::fake()->createWithContent('k.pdf', '%PDF-1.4'), 'change_reason' => 'awal',
            ]);
        $document = Document::where('title', 'Kontrak Rahasia')->firstOrFail();
        $version = $document->versions()->firstOrFail();

        $this->actingAs($otherUser)->withSession(['company_id' => $other->id]);
        $this->get("/admin/documents/{$document->id}")->assertNotFound();
        $this->get("/admin/document-versions/{$version->id}/download")->assertNotFound();

        // Index perusahaan lain tidak menampilkan dokumen GP.
        $index = $this->get('/admin/documents')->assertOk()->getContent();
        $this->assertStringNotContainsString('Kontrak Rahasia', $index);
    }

    public function test_pagination_and_search_filters(): void
    {
        $this->givePermissions(['document.view', 'document.manage']);
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);

        for ($i = 1; $i <= 25; $i++) {
            $this->post('/admin/documents', [
                'document_type' => 'report',
                'title' => ($i === 25 ? 'Laporan Khusus Beton' : "Laporan Rutin {$i}"),
                'file' => UploadedFile::fake()->createWithContent("d{$i}.pdf", '%PDF-1.4'),
                'change_reason' => "Alasan {$i}",
            ]);
        }
        $this->assertSame(25, DB::table('documents')->count());

        // Pagination 20/halaman + link halaman 2 (hitung tautan baris secara presisi).
        $page1 = $this->get('/admin/documents')->assertOk()->getContent();
        $rows = preg_match_all('/href="[^"]*\/admin\/documents\/\d+"/', $page1);
        $this->assertSame(20, $rows, 'Jumlah baris dokumen: '.$rows);
        $this->assertStringContainsString('page=2', $page1);

        // Pencarian mempersempit daftar.
        $search = $this->get('/admin/documents?q=Khusus+Beton')->assertOk()->getContent();
        $this->assertSame(1, preg_match_all('/Laporan Khusus Beton/', $search));
        $this->assertStringNotContainsString('Laporan Rutin 3</a>', $search);

        // Filter jenis dokumen. 25 dokumen dibuat pada detik yang sama — urutan
        // created_at yang seri membuat penempatan baris spesifik antar halaman
        // tidak deterministik (tie-break SQLite), jadi asersi berbasis jumlah baris.
        $type = $this->get('/admin/documents?type=report')->assertOk()->getContent();
        $this->assertSame(20, preg_match_all('/href="[^"]*\/admin\/documents\/\d+"/', $type));
        $this->assertStringContainsString('page=2', $type);
    }

    public function test_empty_state_shown_when_no_documents(): void
    {
        $this->givePermissions(['document.view']);
        $html = $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get('/admin/documents')->assertOk()->getContent();
        $this->assertStringContainsString('Belum ada dokumen', $html);
    }

    public function test_view_permission_cannot_create_but_manage_can(): void
    {
        $this->givePermissions(['document.view']);
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post('/admin/documents', [
                'document_type' => 'contract', 'title' => 'X',
                'file' => UploadedFile::fake()->createWithContent('x.pdf', '%PDF-1.4'), 'change_reason' => 'y',
            ])
            ->assertForbidden();
        $this->assertSame(0, DB::table('documents')->count());

        $this->givePermissions(['document.manage']);
        $this->post('/admin/documents', [
            'document_type' => 'contract', 'title' => 'X',
            'file' => UploadedFile::fake()->createWithContent('x.pdf', '%PDF-1.4'), 'change_reason' => 'y',
        ])->assertRedirect();
        $this->assertSame(1, DB::table('documents')->count());
    }
}
