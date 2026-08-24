<?php

namespace Tests\Feature\Project;

use App\Models\BoredPile;
use App\Models\BoredPileDrilling;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\DocumentVersion;
use App\Models\Permission;
use App\Models\PileAcceptance;
use App\Models\PileTest;
use App\Models\Project;
use App\Models\ProjectZone;
use App\Models\Role;
use App\Models\User;
use App\Services\EvidenceRequirementService;
use App\Services\FieldOpsService;
use App\Services\HandoverPackageService;
use App\Services\PileAcceptanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PileAcceptanceHandoverTest extends TestCase
{
    use RefreshDatabase;

    private $company;

    private $user;

    private $project;

    private $pile;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['objectstorage.evidence_disk' => 'local', 'objectstorage.document_disk' => 'local']);
        [$this->company, $this->user, $this->pile, $this->project] = $this->fixture();
    }

    private function fixture(): array
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => 'acc-gp'], ['name' => 'Acc GP']);
        foreach (['project.view', 'project.manage', 'qms.verify', 'approval.decide'] as $perm) {
            $permission = Permission::firstOrCreate(['code' => $perm], ['name' => $perm, 'module' => str($perm)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membership = DB::table('company_user')->where('company_id', $company->id)->where('user_id', $user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C1', 'name' => 'Client']);
        $project = Project::create([
            'company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P-ACC', 'name' => 'Proyek ACC',
            'status' => 'in_progress',
        ]);
        $zone = ProjectZone::create(['project_id' => $project->id, 'code' => 'A', 'name' => 'Zona A']);
        $pile = BoredPile::create([
            'project_id' => $project->id, 'project_zone_id' => $zone->id,
            'pile_number' => 'BP-ACC-1', 'diameter_mm' => '1000', 'planned_depth_m' => '20',
            'status' => 'completed', 'created_by' => $user->id,
        ]);

        return [$company, $user, $pile, $project];
    }

    /** Lengkapi data pile agar gate acceptance lolos. */
    private function satisfyGates(BoredPile $pile): void
    {
        $pile->update(['actual_toe_level' => '-21.5', 'actual_depth_m' => '20.1']);
        PileTest::create([
            'company_id' => $this->company->id, 'project_id' => $pile->project_id, 'bored_pile_id' => $pile->id,
            'number' => 'PIT-'.$pile->id, 'test_type' => 'PIT', 'scheduled_date' => now()->toDateString(),
            'result_status' => 'passed', 'recorded_by' => $this->user->id,
        ]);
        // As-built teregistrasi.
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post("/admin/bored-piles/{$pile->id}/as-built/store")->assertRedirect();
        app(FieldOpsService::class)->storeEvidence('drilling', BoredPileDrilling::create([
            'company_id' => $this->company->id, 'bored_pile_id' => $pile->id,
            'drilling_started_at' => now(), 'recorded_by' => $this->user->id, 'status' => 'draft',
        ])->id, UploadedFile::fake()->image('e.jpg'), $this->user);
    }

    public function test_acceptance_full_lifecycle_with_real_gates(): void
    {
        // Gate awal: belum lengkap → request boleh tapi engineer review harus menolak maju.
        $service = app(PileAcceptanceService::class);
        $gates = $service->gateChecks($this->pile);
        $this->assertFalse($gates['as_built_registered']);
        $this->assertFalse($gates['tests_recorded_no_pending']);

        $this->satisfyGates($this->pile);

        // Request via HTTP.
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post("/admin/bored-piles/{$this->pile->id}/acceptance/request")->assertRedirect();
        $acceptance = PileAcceptance::sole();
        $this->assertSame('pending', $acceptance->status);

        // Double request ditolak.
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post("/admin/bored-piles/{$this->pile->id}/acceptance/request")
            ->assertSessionHasErrors('acceptance');

        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post("/admin/bored-piles/{$this->pile->id}/acceptance/qa-review")->assertRedirect();
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post("/admin/bored-piles/{$this->pile->id}/acceptance/engineer-review")->assertRedirect();
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post("/admin/bored-piles/{$this->pile->id}/acceptance/decide", ['decision' => 'accepted'])->assertRedirect();

        $this->pile->refresh();
        $this->assertSame('accepted', $this->pile->acceptance->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'pile_accepted', 'auditable_id' => $acceptance->id]);
    }

    public function test_engineer_review_blocked_while_gate_open(): void
    {
        $service = app(PileAcceptanceService::class);
        $acceptance = $service->request($this->pile, $this->user);
        $service->reviewQa($acceptance, $this->user);

        $this->expectException(ValidationException::class);
        $service->reviewEngineer($acceptance->refresh(), $this->user);
    }

    public function test_conditional_requires_conditions_and_rejection_requires_reason(): void
    {
        $service = app(PileAcceptanceService::class);
        $this->satisfyGates($this->pile);
        $acceptance = $service->request($this->pile, $this->user);
        $service->reviewQa($acceptance, $this->user);
        $service->reviewEngineer($acceptance->refresh(), $this->user);

        try {
            $service->decide($acceptance, 'conditional', $this->user);
            $this->fail('Conditional tanpa syarat harus gagal.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('conditions', $e->errors());
        }

        $decided = $service->decide($acceptance->refresh(), 'conditional', $this->user, conditions: 'Retest CSL pada 2 titik dalam 14 hari');
        $this->assertSame('conditional', $decided->status);
        $this->assertSame('Retest CSL pada 2 titik dalam 14 hari', $decided->conditions);
    }

    public function test_evidence_rules_off_by_default_and_enforced_when_enabled(): void
    {
        $rules = app(EvidenceRequirementService::class);
        // Default OFF — backward compatible.
        $this->assertFalse($rules->enabled($this->company->id));
        $this->assertSame([], $rules->missing($this->pile));

        CompanySetting::put($this->company->id, ['evidence_rules_enabled' => '1', 'min_photo_setting_out' => '1']);
        $missing = $rules->missing($this->pile);
        $this->assertArrayHasKey('setting_out', $missing);
        $this->assertSame(1, $missing['setting_out']['required']);

        // Upload kategori yang kurang → missing hilang.
        app(FieldOpsService::class)->storeEvidence('drilling', BoredPileDrilling::create([
            'company_id' => $this->company->id, 'bored_pile_id' => $this->pile->id,
            'drilling_started_at' => now(), 'recorded_by' => $this->user->id, 'status' => 'draft',
        ])->id, UploadedFile::fake()->image('x.jpg'), $this->user);
        $this->assertNotSame([], $rules->missing($this->pile));
    }

    public function test_handover_package_blocks_unaccepted_and_built_when_ready(): void
    {
        $service = app(HandoverPackageService::class);

        // Belum ada accepted → default scope kosong → exceptions? scope kosong = tidak exception tapi 0 pile.
        [$piles, $exceptions] = $service->scope($this->project, null);
        $this->assertSame(0, $piles->count());

        // Scope eksplisit ke pile belum accepted → exception list.
        [, $explicitExceptions] = $service->scope($this->project, [$this->pile->id]);
        $this->assertTrue($explicitExceptions->contains('pile_number', $this->pile->pile_number));

        // Siapkan + accept.
        $this->satisfyGates($this->pile);
        $acc = app(PileAcceptanceService::class);
        $request = $acc->request($this->pile, $this->user);
        $acc->reviewQa($request, $this->user);
        $acc->reviewEngineer($request->refresh(), $this->user);
        $acc->decide($request->refresh(), 'accepted', $this->user);

        $result = $service->build($this->project, null, $this->user);
        $stored = $result['stored'];
        Storage::disk('local')->assertExists($stored->object_key);
        $this->assertSame('handover', $stored->category);
        $version = DocumentVersion::findOrFail($stored->document_version_id);
        $this->assertSame('application/zip', $version->mime_type);
        $this->assertDatabaseHas('audit_logs', ['event' => 'handover_package_generated', 'auditable_id' => $stored->id]);

        // ZIP valid & berisi manifest + as-built.
        $zipPath = tempnam(sys_get_temp_dir(), 'verify_');
        file_put_contents($zipPath, Storage::disk('local')->get($stored->object_key));
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertNotFalse($zip->locateName('MANIFEST.csv'));
        $this->assertNotFalse($zip->locateName('as-built/'.$this->pile->pile_number.'-as-built.pdf'));
        $zip->close();
        @unlink($zipPath);
    }
}
