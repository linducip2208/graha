<?php

namespace Tests\Feature\Foundation;

use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentSignature;
use App\Models\DocumentVersion;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentSignatureService;
use App\Services\DocumentVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignatureVerifyBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_page_shows_valid_result_and_detects_tampering(): void
    {
        [$company, $user, $version] = $this->fixture();
        $signature = app(DocumentSignatureService::class)->signInternal($version, $user, 'Direktur');
        $token = $signature->verificationToken();

        $this->get('/verify/'.$token)
            ->assertOk()
            ->assertSee('TERVERIFIKASI')
            ->assertSee($signature->signed_hash);

        // File di storage diubah diam-diam -> verifikasi wajib gagal.
        Storage::disk('local')->put($version->path, '%PDF-1.4 TAMPERED CONTENT');

        $this->get('/verify/'.$token)
            ->assertOk()
            ->assertSee('GAGAL VERIFIKASI');
    }

    public function test_verify_token_is_unguessable(): void
    {
        [, $user, $version] = $this->fixture();
        app(DocumentSignatureService::class)->signInternal($version, $user, 'Direktur');
        $good = explode('-', DocumentSignature::where('document_version_id', $version->id)->first()->verificationToken());

        $this->get('/verify/999-'.($good[1] ?? 'deadbeef'))->assertNotFound();
        $this->get('/verify/not-a-token')->assertNotFound();
    }

    public function test_batch_sign_signs_owned_unsigned_versions_and_skips_signed(): void
    {
        [$company, $user] = $this->fixtureCompany();
        $this->grantSignPermission($company, $user);
        [$other, , $foreignVersion] = $this->fixture('XX', $user);
        $v1 = $this->makeVersion($company, $user);
        $v2 = $this->makeVersion($company, $user);
        app(DocumentSignatureService::class)->signInternal($v2, $user, 'Direktur');

        $this->actingAs($user)->withSession(['company_id' => $company->id])
            ->from('/admin/signatures')
            ->post('/admin/signatures/batch-internal', [
                'version_ids' => [$v1->id, $v2->id, $foreignVersion->id],
                'position' => 'Manajer Proyek',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $status) => str_contains($status, '1 ditandatangani') && str_contains($status, '1 dilewati'));

        $this->assertTrue($v1->refresh()->is_signed);
        $this->assertTrue($v2->refresh()->is_signed);
        $this->assertFalse($foreignVersion->refresh()->is_signed);
    }

    private function fixture(string $code = 'GP', ?User $existingUser = null): array
    {
        Storage::fake('local');
        $company = Company::create(['code' => $code, 'name' => 'Company '.$code]);
        $user = $existingUser ?? User::factory()->create();

        return [$company, $user, $this->makeVersion($company, $user)];
    }

    private function fixtureCompany(string $code = 'GP'): array
    {
        Storage::fake('local');
        $company = Company::create(['code' => $code.uniqid(), 'name' => 'Batch Co']);
        $user = User::factory()->create();
        $user->companies()->attach($company, ['is_default' => true, 'is_active' => true]);

        return [$company, $user];
    }

    /** Permission signature.* harus lewat role per membership (backend authorization). */
    private function grantSignPermission(Company $company, User $user): void
    {
        $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => 'signer-'.md5($company->id.$user->id)], ['name' => 'Signer']);
        foreach (['signature.sign', 'signature.view'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => 'Signature '.$code, 'module' => 'signature']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membership = DB::table('company_user')->where('company_id', $company->id)->where('user_id', $user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);
    }

    private function makeVersion(Company $company, User $user): DocumentVersion
    {
        $document = Document::create(['company_id' => $company->id, 'document_type' => 'contract', 'number' => fake()->unique()->numerify('DOC-####'), 'title' => 'Kontrak Batch', 'owner_id' => $user->id]);
        $file = UploadedFile::fake()->createWithContent('contract.pdf', '%PDF-1.4 content '.uniqid());

        return app(DocumentVersionService::class)->add($document, $file, $user, 'Initial');
    }
}
