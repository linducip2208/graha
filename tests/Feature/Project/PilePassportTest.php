<?php

namespace Tests\Feature\Project;

use App\Models\BoredPile;
use App\Models\Company;
use App\Models\Customer;
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
use Tests\TestCase;

class PilePassportTest extends TestCase
{
    use RefreshDatabase;

    private $company;

    private $user;

    private $pile;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->company, $this->user, $this->pile] = $this->fixture();
        Storage::fake('local');
        config(['objectstorage.evidence_disk' => 'local']);
    }

    private function fixture(string $code = 'GP'): array
    {
        $company = Company::create(['code' => $code, 'name' => $code]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => 'pp-'.$code], ['name' => 'PP '.$code]);
        foreach (['project.view', 'project.manage'] as $perm) {
            $permission = Permission::firstOrCreate(['code' => $perm], ['name' => $perm, 'module' => str($perm)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membership = DB::table('company_user')->where('company_id', $company->id)->where('user_id', $user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C'.$code, 'name' => 'Client']);
        $project = Project::create([
            'company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P-'.$code, 'name' => 'Proyek '.$code,
            'status' => 'in_progress', 'overbreak_tolerance_percent' => '10',
        ]);
        $pile = BoredPile::create([
            'project_id' => $project->id,
            'project_zone_id' => ProjectZone::create(['project_id' => $project->id, 'code' => 'A', 'name' => 'Zona A'])->id,
            'pile_number' => 'BP-A-021', 'diameter_mm' => '1000', 'planned_depth_m' => '22',
            'status' => 'testing', 'created_by' => $user->id,
        ]);

        return [$company, $user, $pile];
    }

    public function test_passport_renders_identity_timeline_qr_and_photos(): void
    {
        app(EvidenceStorageService::class)->storePilePhoto(
            $this->pile, 'drilling', UploadedFile::fake()->image('drill.jpg'), $this->user, ['caption' => 'Drilling progress']
        );
        app(EvidenceStorageService::class)->storePilePhoto(
            $this->pile, 'completion', UploadedFile::fake()->image('done.jpg'), $this->user
        );

        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get("/admin/bored-piles/{$this->pile->id}/passport")
            ->assertOk()
            ->assertSee('Digital Pile Passport — BP-A-021')
            ->assertSee('Timeline Foto Evidence')
            ->assertSee('/piles/'.$this->pile->public_uuid)
            ->assertSee('<svg', false)
            ->assertSee('Drilling progress');

        // Audit passport viewed terekam.
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'pile_passport_viewed',
            'auditable_id' => $this->pile->id,
            'auditable_type' => BoredPile::class,
        ]);
    }

    public function test_photo_upload_from_passport_is_categorized_and_versioned_with_variants(): void
    {
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post("/admin/bored-piles/{$this->pile->id}/photos", [
                'category' => 'bottom_cleaning',
                'caption' => 'Dasar lubang bersih',
                'file' => UploadedFile::fake()->image('clean.jpg'),
                'latitude' => '-6.2',
                'longitude' => '106.816',
            ])
            ->assertRedirect();

        $photo = StoredFile::where('bored_pile_id', $this->pile->id)->whereNull('original_file_id')->sole();
        $this->assertSame('bottom_cleaning', $photo->sub_category);
        $this->assertSame('Dasar lubang bersih', $photo->caption);
        // Original + preview + thumb = 3 baris metadata.
        $this->assertSame(3, StoredFile::where('bored_pile_id', $this->pile->id)->count());
        $this->assertEquals('-6.20000000', $photo->latitude);
    }

    public function test_public_qr_entry_redirects_guest_to_login_then_back(): void
    {
        $entry = '/piles/'.$this->pile->public_uuid;

        // Guest: redirect login dengan intended kembali ke pile.
        $this->get($entry)->assertRedirect(route('login'));
        $this->followRedirects($this->get($entry))
            ->assertOk()
            ->assertSee('Masuk');

        // Member aktif: langsung menuju passport.
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get($entry)
            ->assertRedirect("/admin/bored-piles/{$this->pile->id}/passport");
    }

    public function test_public_qr_entry_unknown_uuid_is_404(): void
    {
        $this->get('/piles/00000000-0000-0000-0000-000000000000')->assertNotFound();
    }
}
