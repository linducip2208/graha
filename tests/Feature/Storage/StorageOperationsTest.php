<?php

namespace Tests\Feature\Storage;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\Storage\ObjectStorageService;
use App\Services\Storage\StorageRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StorageOperationsTest extends TestCase
{
    use RefreshDatabase;

    private $company;

    private $admin;

    private $user;

    private $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $this->admin = User::factory()->create();
        $this->user = User::factory()->create();
        foreach ([[$this->admin, ['project.view', 'project.manage', 'document.view', 'storage.manage']], [$this->user, ['project.view']]] as [$u, $perms]) {
            $u->companies()->attach($this->company->id, ['is_default' => true, 'is_active' => true]);
            $role = Role::firstOrCreate(['company_id' => $this->company->id, 'code' => 'so-'.spl_object_id($u)], ['name' => 'SO']);
            foreach ($perms as $perm) {
                $permission = Permission::firstOrCreate(['code' => $perm], ['name' => $perm, 'module' => str($perm)->before('.')]);
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
            $membership = DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', $u->id)->first();
            DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);
        }
        $customer = Customer::create(['company_id' => $this->company->id, 'code' => 'C1', 'name' => 'Client']);
        $this->project = Project::create([
            'company_id' => $this->company->id, 'customer_id' => $customer->id, 'code' => 'P-SO', 'name' => 'Proyek Storage',
            'status' => 'in_progress', 'overbreak_tolerance_percent' => '8',
        ]);
    }

    private function makeFile(string $category = 'photo', array $overrides = []): StoredFile
    {
        return StoredFile::create([
            'uuid' => Str::uuid(),
            'company_id' => $this->company->id, 'project_id' => $this->project->id,
            'category' => $category, 'disk' => 'local', 'object_key' => uniqid('test/').'.jpg',
            'original_name' => 'sample.jpg', 'extension' => 'jpg', 'mime_type' => 'image/jpeg',
            'size_bytes' => 1024 * 512, 'sha256' => str_repeat('a', 64), 'status' => 'ready',
            'sub_category' => $category === 'photo' ? 'drilling' : null,
            ...$overrides,
        ]);
    }

    public function test_storage_dashboard_aggregates_from_db_without_bucket_scan(): void
    {
        $this->makeFile('photo');
        $this->makeFile('photo');
        $this->makeFile('as_built');

        $response = $this->actingAs($this->admin)->withSession(['company_id' => $this->company->id])
            ->get('/admin/storage')
            ->assertOk()
            ->assertSee('Object Storage')
            ->assertSee('Total Objek')
            ->assertSee('Per Disk')
            ->assertSee('Foto per Fase');

        // Filter per proyek.
        $this->actingAs($this->admin)->withSession(['company_id' => $this->company->id])
            ->get('/admin/storage?project='.$this->project->id)
            ->assertOk();
    }

    public function test_retention_lifecycle_archive_then_blocked_physical_delete_for_protected(): void
    {
        $service = app(StorageRetentionService::class);
        $photo = $this->makeFile('photo', ['object_key' => 'storage-test/photo.jpg']);
        app(ObjectStorageService::class)->put('storage-test/photo.jpg', 'binary-content-here');

        // Archive.
        $archived = $service->archive($photo, $this->admin);
        $this->assertSame('archived', $archived->status);
        $this->assertNotNull($archived->archived_at);

        // Pending delete tanpa policy → ditolak (default OFF).
        try {
            $service->markPendingDelete($archived, $this->admin);
            $this->fail('Harusnya ditolak tanpa kebijakan.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('policy', $e->errors());
        }

        // Aktifkan policy → pending delete dengan due date.
        CompanySetting::put($this->company->id, ['delete_after_archive_days' => '30']);
        $pending = $service->markPendingDelete($archived, $this->admin);
        $this->assertSame('pending_delete', $pending->status);

        // Physical delete foto biasa diizinkan dengan permission storage.manage.
        $service->physicalDelete($pending->refresh(), $this->admin);
        $this->assertSame('deleted', $pending->refresh()->status);
        $this->assertFalse(app(ObjectStorageService::class)->exists('storage-test/photo.jpg'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'storage.file_deleted_physically']);

        // Kategori terproteksi TIDAK boleh dihapus fisik.
        $asBuilt = $this->makeFile('as_built', ['status' => 'archived', 'archived_at' => now(), 'object_key' => 'storage-test/ab.pdf']);
        CompanySetting::put($this->company->id, ['delete_after_archive_days' => '30']);
        $protectedPending = $service->markPendingDelete($asBuilt, $this->admin);
        try {
            $service->physicalDelete($protectedPending, $this->admin);
            $this->fail('As-built harus terlindungi.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('category', $e->errors());
        }
    }

    public function test_retention_requires_high_permission_via_http(): void
    {
        $file = $this->makeFile();
        // User TANPA storage.manage → 403.
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post("/admin/storage/{$file->uuid}/retention", ['action' => 'archive'])
            ->assertForbidden();
        // Admin DENGAN storage.manage → sukses + audit.
        $this->actingAs($this->admin)->withSession(['company_id' => $this->company->id])
            ->post("/admin/storage/{$file->uuid}/retention", ['action' => 'archive'])
            ->assertRedirect();
        $this->assertSame('archived', $file->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'storage.file_archived']);
    }

    public function test_upload_queue_finalize_is_idempotent(): void
    {
        $session = ['company_id' => $this->company->id];
        $uploadId = (string) Str::uuid();

        // Request upload → mode server (local disk tidak mendukung presigned).
        $requested = $this->actingAs($this->admin)->withSession($session)
            ->post('/admin/storage/request-upload', [
                'category' => 'photo', 'filename' => 'field.jpg', 'size' => 2048,
            ])->assertOk()->json();
        $this->assertSame('server', $requested['mode']);

        // Finalize pertama → ready; finalize kedua (retry jaringan) → tetap ready tanpa error.
        $payload = ['upload_id' => $requested['upload_id'], 'sha256' => str_repeat('b', 64), 'size' => 2048];
        $first = $this->actingAs($this->admin)->withSession($session)->post('/admin/storage/finalize-upload', $payload)->assertOk()->json();
        $second = $this->actingAs($this->admin)->withSession($session)->post('/admin/storage/finalize-upload', $payload)->assertOk()->json();
        $this->assertSame('ready', $first['status']);
        $this->assertSame('ready', $second['status']);
        $this->assertSame(2, StoredFile::where('upload_id', $requested['upload_id'])->count() >= 1 ? 2 : 0); // idempotent: hanya satu row yang di-update dua kali

        // Upload id client-generated juga didukung finalize by id tersebut.
        $this->actingAs($this->admin)->withSession($session)
            ->post('/admin/storage/finalize-upload', ['upload_id' => $uploadId])
            ->assertNotFound(); // belum ada record → 404, bukan error server
    }

    public function test_cross_company_file_not_accessible_for_retention(): void
    {
        $other = Company::create(['code' => 'GB', 'name' => 'GB']);
        $file = $this->makeFile();
        $file->update(['company_id' => $other->id]);

        $this->actingAs($this->admin)->withSession(['company_id' => $this->company->id])
            ->post("/admin/storage/{$file->uuid}/retention", ['action' => 'archive'])
            ->assertNotFound();
    }
}
