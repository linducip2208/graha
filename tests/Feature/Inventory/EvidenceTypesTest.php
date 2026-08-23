<?php

namespace Tests\Feature\Inventory;

use App\Models\CasingUnit;
use App\Models\Company;
use App\Models\FieldEvidence;
use App\Models\Permission;
use App\Models\ReinforcementCage;
use App\Models\Role;
use App\Models\Tool;
use App\Models\User;
use App\Services\FieldOpsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EvidenceTypesTest extends TestCase
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
        $this->givePermissions(['project.view']);
        Storage::fake('local');
    }

    private function givePermissions(array $codes): void
    {
        $role = Role::firstOrCreate(['company_id' => $this->company->id, 'code' => 'evidence-role'], ['name' => 'Evidence Role']);
        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => str($code)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membership = DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', $this->user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);
    }

    public function test_tool_evidence_upload_download_and_company_isolation(): void
    {
        $tool = Tool::create(['company_id' => $this->company->id, 'code' => 'TL-EV-1', 'name' => 'Bosch GBH 2-26']);
        $other = Company::create(['code' => 'GB', 'name' => 'Graha Baru']);
        // Outsider punya permission di company-nya sendiri; 404 membuktikan isolasi data, bukan sekadar gate permission.
        $outsider = User::factory()->create();
        $outsider->companies()->attach($other->id, ['is_default' => true, 'is_active' => true]);

        $evidence = app(FieldOpsService::class)->storeEvidence('tool', $tool->id, UploadedFile::fake()->image('kondisi-alat.jpg'), $this->user);

        $this->assertDatabaseHas('field_evidences', ['id' => $evidence->id, 'evidence_type' => 'tool', 'disk' => 'local', 'company_id' => $this->company->id]);
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get("/admin/field-evidence/{$evidence->id}/download")
            ->assertOk();
        $roleB = Role::firstOrCreate(['company_id' => $other->id, 'code' => 'viewer-b'], ['name' => 'Viewer B']);
        $permission = Permission::firstOrCreate(['code' => 'project.view'], ['name' => 'project.view', 'module' => 'project']);
        $roleB->permissions()->syncWithoutDetaching([$permission->id]);
        $membershipB = DB::table('company_user')->where('company_id', $other->id)->where('user_id', $outsider->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membershipB->id, 'role_id' => $roleB->id]);
        $this->actingAs($outsider)->withSession(['company_id' => $other->id])
            ->get("/admin/field-evidence/{$evidence->id}/download")
            ->assertNotFound();
    }

    public function test_rejects_non_image_and_oversize(): void
    {
        $tool = Tool::create(['company_id' => $this->company->id, 'code' => 'TL-EV-2', 'name' => 'Genset Portable']);
        $service = app(FieldOpsService::class);

        try {
            $service->storeEvidence('tool', $tool->id, UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'), $this->user);
            $this->fail('PDF harus ditolak.');
        } catch (ValidationException) {
            $this->assertSame(0, FieldEvidence::count());
        }

        try {
            $service->storeEvidence('tool', $tool->id, UploadedFile::fake()->create('besar.png', 6000, 'image/png'), $this->user);
            $this->fail('File > 5MB harus ditolak.');
        } catch (ValidationException) {
            $this->assertSame(0, FieldEvidence::count());
        }
    }

    public function test_cage_and_casing_evidence_types_resolve_subjects(): void
    {
        $cage = ReinforcementCage::create(['company_id' => $this->company->id, 'number' => 'CAGE-EV-1', 'diameter_mm' => '700', 'total_length_m' => '17.5', 'created_by' => $this->user->id]);
        $casing = CasingUnit::create(['company_id' => $this->company->id, 'code' => 'CS-EV-1', 'diameter_mm' => '800', 'length_m' => '6', 'ownership' => 'owned', 'status' => 'in_stock', 'created_by' => $this->user->id]);
        $service = app(FieldOpsService::class);

        foreach (['cage' => $cage->id, 'casing' => $casing->id] as $type => $id) {
            $evidence = $service->storeEvidence($type, $id, UploadedFile::fake()->image('foto.jpg'), $this->user);
            $this->assertSame($type, $evidence->evidence_type);
            $this->assertSame('local', $evidence->disk);
        }
        $this->assertSame(2, FieldEvidence::count());
    }
}
