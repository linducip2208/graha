<?php

namespace Tests\Feature\Storage;

use App\Models\Company;
use App\Models\CompanyStorageProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Storage\CompanyStorageManager;
use App\Services\Storage\StorageConnectionTester;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyStorageProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_credentials_are_encrypted_hidden_and_generic_s3_config_is_built(): void
    {
        $company = Company::create(['code' => 'ST', 'name' => 'Storage Test']);
        $profile = CompanyStorageProfile::create([
            'company_id' => $company->id, 'name' => 'R2 UX Preset', 'driver' => 's3', 'provider_preset' => 'cloudflare-r2',
            'endpoint' => 'https://account.r2.cloudflarestorage.com', 'region' => 'auto', 'bucket' => 'private-bucket',
            'access_key_encrypted' => 'ACCESS-A8X2', 'secret_key_encrypted' => 'SUPER-SECRET', 'use_path_style_endpoint' => true,
        ]);
        $raw = DB::table('company_storage_profiles')->find($profile->id);
        $this->assertStringNotContainsString('ACCESS-A8X2', $raw->access_key_encrypted);
        $this->assertStringNotContainsString('SUPER-SECRET', $raw->secret_key_encrypted);
        $this->assertArrayNotHasKey('secret_key_encrypted', $profile->toArray());
        $config = app(CompanyStorageManager::class)->config($profile);
        $this->assertSame('s3', $config['driver']);
        $this->assertSame('https://account.r2.cloudflarestorage.com', $config['endpoint']);
        $this->assertTrue($config['use_path_style_endpoint']);
    }

    public function test_company_profile_resolution_and_local_fallback(): void
    {
        $a = Company::create(['code' => 'A', 'name' => 'A']);
        $b = Company::create(['code' => 'B', 'name' => 'B']);
        CompanyStorageProfile::create(['company_id' => $a->id, 'name' => 'A Local', 'driver' => 'local', 'provider_preset' => 'custom', 'is_active' => true, 'is_default_evidence' => true, 'status' => 'connected']);
        $manager = app(CompanyStorageManager::class);
        $this->assertStringStartsWith('profile:', $manager->resolve($a->id, 'evidence')['disk']);
        config(['objectstorage.evidence_disk' => 'missing-disk']);
        $this->assertSame('local', $manager->resolve($b->id, 'evidence')['disk']);
    }

    public function test_normal_user_cannot_manage_and_company_cannot_see_other_profile(): void
    {
        [$companyA, $admin] = $this->membership('CA', true);
        [$companyB] = $this->membership('CB', false);
        $foreign = CompanyStorageProfile::create(['company_id' => $companyB->id, 'name' => 'Foreign', 'driver' => 'local', 'provider_preset' => 'custom']);
        $this->actingAs($admin)->withSession(['company_id' => $companyA->id])->post(route('storage-profiles.disable', $foreign))->assertNotFound();

        [, $normal] = $this->membership('CC', false);
        $this->actingAs($normal)->withSession(['company_id' => $normal->companies()->first()->id])->get(route('settings.storage'))->assertForbidden();
    }

    public function test_secret_is_never_rendered_in_storage_ui(): void
    {
        [$company, $admin] = $this->membership('UI', true);
        CompanyStorageProfile::create(['company_id' => $company->id, 'name' => 'Private', 'driver' => 's3', 'provider_preset' => 'custom', 'endpoint' => 'https://s3.example.test', 'region' => 'id', 'bucket' => 'b', 'access_key_encrypted' => 'VISIBLE-A8X2', 'secret_key_encrypted' => 'NEVER-RENDER-ME']);
        $this->actingAs($admin)->withSession(['company_id' => $company->id])->get(route('settings.storage'))->assertOk()->assertDontSee('NEVER-RENDER-ME')->assertSee('A8X2');
    }

    public function test_connection_tester_reports_success_and_failure_safely(): void
    {
        $company = Company::create(['code' => 'HC', 'name' => 'Health Check']);
        $profile = CompanyStorageProfile::create(['company_id' => $company->id, 'name' => 'Local', 'driver' => 'local', 'provider_preset' => 'custom']);
        $disk = \Mockery::mock(Filesystem::class);
        $payload = null;
        $disk->shouldReceive('files')->once()->andReturn([]);
        $disk->shouldReceive('put')->once()->andReturnUsing(function ($key, $value) use (&$payload) {
            $payload = $value;

            return true;
        });
        $disk->shouldReceive('get')->once()->andReturnUsing(function () use (&$payload) {
            return $payload;
        });
        $disk->shouldReceive('delete')->once()->andReturnTrue();
        $disk->shouldReceive('exists')->once()->andReturnFalse();
        $manager = \Mockery::mock(CompanyStorageManager::class);
        $manager->shouldReceive('build')->once()->andReturn($disk);
        $this->assertSame('CONNECTED', (new StorageConnectionTester($manager))->test($profile)['status']);

        $failedManager = \Mockery::mock(CompanyStorageManager::class);
        $failedManager->shouldReceive('build')->once()->andThrow(new \RuntimeException('raw credential detail must stay hidden'));
        $result = (new StorageConnectionTester($failedManager))->test($profile);
        $this->assertSame('FAILED', $result['status']);
        $this->assertStringNotContainsString('raw credential detail', $result['message']);
    }

    private function membership(string $code, bool $manage): array
    {
        $company = Company::create(['code' => $code, 'name' => $code]);
        $user = User::factory()->create();
        $user->companies()->attach($company, ['is_default' => true, 'is_active' => true]);
        $role = Role::create(['company_id' => $company->id, 'code' => strtolower($code), 'name' => $code]);
        if ($manage) {
            $permission = Permission::firstOrCreate(['code' => 'storage.manage'], ['name' => 'storage.manage', 'module' => 'storage']);
            $role->permissions()->attach($permission);
        }
        $membership = DB::table('company_user')->where('company_id', $company->id)->where('user_id', $user->id)->first();
        DB::table('company_user_role')->insert(['company_user_id' => $membership->id, 'role_id' => $role->id]);

        return [$company, $user];
    }
}
