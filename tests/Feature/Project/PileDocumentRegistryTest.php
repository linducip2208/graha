<?php

namespace Tests\Feature\Project;

use App\Models\BoredPile;
use App\Models\Company;
use App\Models\Customer;
use App\Models\DocumentVersion;
use App\Models\Permission;
use App\Models\PileTest;
use App\Models\Project;
use App\Models\ProjectZone;
use App\Models\Role;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PileDocumentRegistryTest extends TestCase
{
    use RefreshDatabase;

    private $company;

    private $user;

    private $pile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $this->user = User::factory()->create();
        $this->user->companies()->attach($this->company->id, ['is_default' => true, 'is_active' => true]);
        $role = Role::firstOrCreate(['company_id' => $this->company->id, 'code' => 'reg-gp'], ['name' => 'Reg GP']);
        foreach (['project.view', 'project.manage'] as $perm) {
            $permission = Permission::firstOrCreate(['code' => $perm], ['name' => $perm, 'module' => str($perm)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membership = DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', $this->user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);
        $customer = Customer::create(['company_id' => $this->company->id, 'code' => 'C1', 'name' => 'Client']);
        $project = Project::create([
            'company_id' => $this->company->id, 'customer_id' => $customer->id, 'code' => 'P-GP', 'name' => 'Proyek',
            'status' => 'in_progress',
        ]);
        $this->pile = BoredPile::create([
            'project_id' => $project->id,
            'project_zone_id' => ProjectZone::create(['project_id' => $project->id, 'code' => 'A', 'name' => 'Zona A'])->id,
            'pile_number' => 'BP-R-01', 'diameter_mm' => '1000', 'planned_depth_m' => '20',
            'status' => 'completed', 'created_by' => $this->user->id,
        ]);
        Storage::fake('local');
        config(['objectstorage.evidence_disk' => 'local', 'objectstorage.document_disk' => 'local']);
    }

    private function outsider(): User
    {
        $other = Company::create(['code' => 'GB', 'name' => 'GB']);
        $outsider = User::factory()->create();
        $outsider->companies()->attach($other->id, ['is_default' => true, 'is_active' => true]);
        $role = Role::firstOrCreate(['company_id' => $other->id, 'code' => 'reg-b'], ['name' => 'Reg B']);
        foreach (['project.view', 'project.manage'] as $perm) {
            $permission = Permission::firstOrCreate(['code' => $perm], ['name' => $perm, 'module' => str($perm)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membership = DB::table('company_user')->where('company_id', $other->id)->where('user_id', $outsider->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);

        return $outsider;
    }

    public function test_store_asbuilt_registers_versioned_document_and_stored_file(): void
    {
        PileTest::create([
            'company_id' => $this->company->id, 'project_id' => $this->pile->project_id, 'bored_pile_id' => $this->pile->id,
            'number' => 'PIT-1', 'test_type' => 'PIT', 'scheduled_date' => now()->toDateString(),
            'result_status' => 'passed', 'recorded_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post("/admin/bored-piles/{$this->pile->id}/as-built/store")
            ->assertRedirect()
            ->assertSessionHas('status');

        $version = DocumentVersion::whereHas('document', fn ($q) => $q->where('document_type', 'pile_as_built'))->sole();
        $this->assertSame(1, (int) $version->version);
        $this->assertStringStartsWith('%PDF-', (string) Storage::disk('local')->get($version->path));
        $this->assertSame(64, strlen($version->sha256));

        // StoredFile terhubung ke document_version + binary ada di object storage.
        $stored = StoredFile::where('document_version_id', $version->id)->sole();
        $this->assertSame('as_built', $stored->category);
        $this->assertSame($version->sha256, $stored->sha256);
        Storage::disk('local')->assertExists($stored->object_key);
        $this->assertTrue(str_contains($stored->object_key, '/bored-piles/'.$this->pile->public_uuid.'/as-built/'));

        $this->assertDatabaseHas('audit_logs', ['event' => 'asbuilt_generated', 'auditable_id' => $stored->id]);
    }

    public function test_regenerating_asbuilt_creates_new_version_without_overwrite(): void
    {
        $store = fn () => $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post("/admin/bored-piles/{$this->pile->id}/as-built/store")->assertRedirect();

        $store();
        $store();

        $versions = DocumentVersion::whereHas('document', fn ($q) => $q->where('document_type', 'pile_as_built'))->orderBy('version')->get();
        $this->assertSame(2, $versions->count());
        $this->assertSame([1, 2], $versions->pluck('version')->map(fn ($v) => (int) $v)->all());
        // Kedua file fisik tetap ada — v1 tidak ditimpa (append-only versioning).
        foreach ($versions as $version) {
            Storage::disk('local')->assertExists($version->path);
        }
        $this->assertSame(2, StoredFile::where('category', 'as_built')->count());
    }

    public function test_acceptance_dossier_generated_and_registered(): void
    {
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post("/admin/bored-piles/{$this->pile->id}/dossier/store")
            ->assertRedirect()
            ->assertSessionHas('status');

        $stored = StoredFile::where('category', 'dossier')->sole();
        Storage::disk('local')->assertExists($stored->object_key);
        $this->assertDatabaseHas('audit_logs', ['event' => 'acceptance_dossier_generated', 'auditable_id' => $stored->id]);
        $version = DocumentVersion::findOrFail($stored->document_version_id);
        $this->assertSame(64, strlen($version->sha256));
    }

    public function test_cross_company_cannot_store_documents_for_foreign_pile(): void
    {
        $outsider = $this->outsider();

        $this->actingAs($outsider)->withSession(['company_id' => Company::where('code', 'GB')->first()->id])
            ->post("/admin/bored-piles/{$this->pile->id}/dossier/store")
            ->assertNotFound();
        $this->assertSame(0, DocumentVersion::count());
    }
}
