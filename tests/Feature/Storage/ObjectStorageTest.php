<?php

namespace Tests\Feature\Storage;

use App\Models\BoredPile;
use App\Models\BoredPileDrilling;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FieldEvidence;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectZone;
use App\Models\Role;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\Storage\EvidenceStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ObjectStorageTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Project $project;

    private BoredPile $pile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['code' => 'GP', 'name' => 'Graha Pondasi']);
        $this->user = User::factory()->create();
        $this->user->companies()->attach($this->company, ['is_default' => true, 'is_active' => true]);
        $this->givePermissions(['project.view']);
        $zone = ProjectZone::create(['project_id' => $this->project()->id, 'code' => 'A', 'name' => 'Zona A']);
        $this->pile = BoredPile::create([
            'project_id' => $this->project->id, 'project_zone_id' => $zone->id,
            'pile_number' => 'BP-A-001', 'diameter_mm' => '800', 'planned_depth_m' => '20',
            'created_by' => $this->user->id,
        ]);
        Storage::fake('s3');
        config(['objectstorage.evidence_disk' => 's3']);
    }

    private function project(): Project
    {
        return $this->project ??= Project::create([
            'company_id' => $this->company->id,
            'customer_id' => Customer::create(['company_id' => $this->company->id, 'code' => 'C-GP', 'name' => 'Client Uji'])->id,
            'code' => 'PRJ-01', 'name' => 'Proyek Uji', 'status' => 'in_progress',
        ]);
    }

    private function givePermissions(array $codes): void
    {
        $role = Role::firstOrCreate(['company_id' => $this->company->id, 'code' => 'storage-role'], ['name' => 'Storage Role']);
        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => str($code)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membership = DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', $this->user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);
    }

    public function test_pile_photo_stored_on_s3_with_uuid_key_checksum_and_variants(): void
    {
        $stored = app(EvidenceStorageService::class)->storePilePhoto(
            $this->pile, 'drilling', UploadedFile::fake()->image('foto-drilling.jpg', 800, 600), $this->user,
            ['caption' => 'Proses drilling', 'latitude' => '-6.20000000', 'longitude' => '106.81666667']
        );

        $expectedKeyPrefix = sprintf('companies/%s/projects/%s/bored-piles/%s/photos/drilling/',
            $this->company->uuid, $this->project->uuid, $this->pile->public_uuid);
        $this->assertStringStartsWith($expectedKeyPrefix, $stored->object_key);
        $this->assertSame('ready', $stored->status);
        $this->assertSame(64, strlen($stored->sha256));
        $this->assertNull($stored->captured_at);

        // Binary tersimpan di disk S3 fake; database hanya menyimpan metadata.
        Storage::disk('s3')->assertExists($stored->object_key);
        $this->assertSame(hash_file('sha256', Storage::disk('s3')->path($stored->object_key)), $stored->sha256);

        // Varian thumb & preview dibuat sebagai baris turunan terpisah.
        $variants = StoredFile::where('original_file_id', $stored->id)->get();
        $this->assertSame(['preview', 'thumb'], $variants->pluck('variant_type')->sort()->values()->all());
        $variants->each(function ($variant) {
            Storage::disk('s3')->assertExists($variant->object_key);
            $this->assertTrue(in_array($variant->mime_type, ['image/webp', 'image/jpeg']));
            $this->assertNotEquals('', $variant->sha256);
        });

        // Metadata GPS/caption tersimpan.
        $this->assertSame('Proses drilling', $stored->caption);
        $this->assertEquals('-6.20000000', $stored->latitude);
    }

    public function test_rejects_disguised_non_image_content(): void
    {
        $this->expectException(ValidationException::class);
        app(EvidenceStorageService::class)->storePilePhoto(
            $this->pile, 'drilling',
            UploadedFile::fake()->createWithContent('berbahaya.jpg', '<script>alert("xss")</script>'),
            $this->user
        );
        $this->assertSame(0, StoredFile::count());
    }

    public function test_rejects_unknown_category(): void
    {
        $this->expectException(ValidationException::class);
        app(EvidenceStorageService::class)->storePilePhoto(
            $this->pile, 'kategori-ngawur', UploadedFile::fake()->image('x.jpg'), $this->user
        );
    }

    public function test_authorized_member_can_preview_and_download(): void
    {
        // Jalur streaming lokal: response langsung terotorisasi dari server.
        Storage::fake('local');
        config(['objectstorage.evidence_disk' => 'local']);
        $stored = app(EvidenceStorageService::class)->storePilePhoto(
            $this->pile, 'cage', UploadedFile::fake()->image('cage.jpg'), $this->user
        );

        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get("/admin/files/{$stored->uuid}/preview")
            ->assertOk()
            ->assertHeader('Content-Type', $stored->mime_type);
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get("/admin/files/{$stored->uuid}/download")
            ->assertOk();

        // Varian thumb dapat diakses via parameter variant.
        $thumb = $stored->variant('thumb');
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get("/admin/files/{$stored->uuid}/preview?variant=thumb")
            ->assertOk();
        $this->assertDatabaseHas('audit_logs', ['event' => 'file_uploaded', 'auditable_id' => $stored->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'file_downloaded', 'auditable_id' => $stored->id]);
        $this->assertNotNull($thumb);
    }

    public function test_s3_disk_serves_private_file_via_signed_redirect(): void
    {
        $stored = app(EvidenceStorageService::class)->storePilePhoto(
            $this->pile, 'concrete', UploadedFile::fake()->image('beton.jpg'), $this->user
        );
        $response = $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get("/admin/files/{$stored->uuid}/download");

        // Disk S3-compatible: redirect ke temporary signed URL berbatas waktu.
        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');
        $this->assertTrue(str_contains($target, 'expiration=') || str_contains($target, 'X-Amz-Signature'), 'Redirect harus menuju temporary URL berbatas waktu.');
    }

    public function test_cross_company_access_is_denied_even_by_uuid(): void
    {
        $stored = app(EvidenceStorageService::class)->storePilePhoto(
            $this->pile, 'concrete', UploadedFile::fake()->image('beton.jpg'), $this->user
        );

        $other = Company::create(['code' => 'GB', 'name' => 'Graha Baru']);
        $outsider = User::factory()->create();
        $outsider->companies()->attach($other->id, ['is_default' => true, 'is_active' => true]);
        $roleB = Role::firstOrCreate(['company_id' => $other->id, 'code' => 'viewer-b'], ['name' => 'Viewer B']);
        $permission = Permission::firstOrCreate(['code' => 'project.view'], ['name' => 'project.view', 'module' => 'project']);
        $roleB->permissions()->syncWithoutDetaching([$permission->id]);
        $membershipB = DB::table('company_user')->where('company_id', $other->id)->where('user_id', $outsider->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membershipB->id, 'role_id' => $roleB->id]);

        // UUID file diketahui pun tetap 404 — tidak bisa view/download/enumerate.
        $this->actingAs($outsider)->withSession(['company_id' => $other->id])
            ->get("/admin/files/{$stored->uuid}/download")
            ->assertNotFound();
        $this->actingAs($outsider)->withSession(['company_id' => $other->id])
            ->get("/admin/files/{$stored->uuid}/preview")
            ->assertNotFound();
        $this->actingAs($outsider)->withSession(['company_id' => $other->id])
            ->get("/admin/files/{$stored->uuid}/preview?variant=thumb")
            ->assertNotFound();
    }

    public function test_missing_object_returns_404_not_error(): void
    {
        $stored = app(EvidenceStorageService::class)->storePilePhoto(
            $this->pile, 'slump', UploadedFile::fake()->image('slump.jpg'), $this->user
        );
        Storage::disk('s3')->delete(collect([$stored->object_key])->merge($stored->variants->pluck('object_key'))->all());

        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get("/admin/files/{$stored->uuid}/download")
            ->assertNotFound();
    }

    public function test_field_evidence_upload_creates_linked_stored_file(): void
    {
        $this->givePermissions(['project.manage']);
        $drilling = BoredPileDrilling::create([
            'company_id' => $this->company->id, 'bored_pile_id' => $this->pile->id,
            'drilling_started_at' => now(), 'recorded_by' => $this->user->id, 'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post('/admin/projects/field-ops/evidence/drilling', [
                'id' => $drilling->id,
                'file' => UploadedFile::fake()->image('evidence.jpg'),
            ]);
        $response->assertRedirect();

        $evidence = FieldEvidence::latest('id')->first();
        $this->assertNotNull($evidence->stored_file_id);
        $this->assertNotNull($evidence->storedFile?->sha256);
        Storage::disk('s3')->assertExists($evidence->storedFile->object_key);
    }
}
