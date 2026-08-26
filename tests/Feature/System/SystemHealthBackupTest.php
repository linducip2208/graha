<?php

namespace Tests\Feature\System;

use App\Models\BackupRecord;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemHeartbeat;
use App\Models\User;
use App\Services\BackupService;
use App\Services\PlatformAccessService;
use App\Services\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemHealthBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_heartbeat_changes_health_from_unknown_to_healthy(): void
    {
        $company = Company::create(['code' => 'HC', 'name' => 'Health']);
        $before = collect(app(SystemHealthService::class)->checks($company->id)['checks'])->firstWhere('name', 'Scheduler');
        $this->assertSame('UNKNOWN', $before['status']);
        SystemHeartbeat::create(['key' => 'scheduler', 'last_seen_at' => now(), 'metadata' => ['expected_minutes' => 5]]);
        $after = collect(app(SystemHealthService::class)->checks($company->id)['checks'])->firstWhere('name', 'Scheduler');
        $this->assertSame('HEALTHY', $after['status']);
    }

    public function test_scheduler_heartbeat_event_executes_successfully(): void
    {
        $this->artisan('schedule:test', ['--name' => 'system-heartbeat', '--no-interaction' => true])->assertSuccessful();
        $this->assertDatabaseHas('system_heartbeats', ['key' => 'scheduler']);
    }

    public function test_backup_verify_checks_checksum_gzip_sql_and_blocks_path_traversal(): void
    {
        Storage::fake('local');
        $sql = "-- MySQL dump\nCREATE TABLE example (id int);\n";
        $compressed = gzencode($sql, 9);
        Storage::disk('local')->put('backups/database/valid.sql.gz', $compressed);
        $record = BackupRecord::create(['type' => 'database', 'status' => 'completed', 'disk' => 'local', 'path' => 'backups/database/valid.sql.gz', 'started_at' => now(), 'finished_at' => now(), 'size_bytes' => strlen($compressed), 'sha256' => hash('sha256', $compressed)]);
        $this->assertTrue(app(BackupService::class)->verify($record)['valid']);

        $record->update(['sha256' => str_repeat('0', 64)]);
        $this->assertFalse(app(BackupService::class)->verify($record)['valid']);

        $record->forceFill(['path' => '../.env'])->saveQuietly();
        $this->assertFalse(app(BackupService::class)->verify($record)['valid']);
    }

    public function test_backup_metadata_never_contains_environment_secrets(): void
    {
        $record = BackupRecord::create(['type' => 'database', 'status' => 'failed', 'disk' => 'local', 'started_at' => now(), 'last_error' => 'Backup gagal; periksa server log.']);
        $json = json_encode($record->toArray());
        $this->assertStringNotContainsString('DB_PASSWORD', $json);
        $this->assertStringNotContainsString('APP_KEY', $json);
    }

    public function test_company_storage_manager_cannot_control_platform_operations(): void
    {
        [$company, $user] = $this->companyUser(true);
        $this->actingAs($user)->withSession(['company_id' => $company->id])
            ->get(route('settings.system-health'))->assertForbidden();
        $this->actingAs($user)->withSession(['company_id' => $company->id])
            ->get(route('settings.backups'))->assertForbidden();
        $this->actingAs($user)->withSession(['company_id' => $company->id])
            ->post(route('system-health.jobs'), ['action' => 'delete_all'])->assertForbidden();
    }

    public function test_platform_grants_are_independent_and_least_privilege(): void
    {
        [$company, $user] = $this->companyUser(false);
        $access = app(PlatformAccessService::class);
        $access->grant($user, 'system.view', reason: 'test');
        $this->actingAs($user)->withSession(['company_id' => $company->id])->get(route('settings.system-health'))->assertOk();
        $this->actingAs($user)->withSession(['company_id' => $company->id])->post(route('system-health.jobs'), ['action' => 'delete_all'])->assertForbidden();
        $this->actingAs($user)->withSession(['company_id' => $company->id])->get(route('settings.backups'))->assertForbidden();

        $access->grant($user, 'backup.view', reason: 'test');
        $this->actingAs($user)->withSession(['company_id' => $company->id])->get(route('settings.backups'))->assertOk()->assertDontSee('Buat Backup Database');
    }

    private function companyUser(bool $storageManage): array
    {
        $company = Company::create(['code' => uniqid('SYS'), 'name' => 'System Test']);
        $user = User::factory()->create();
        $user->companies()->attach($company, ['is_default' => true, 'is_active' => true]);
        if ($storageManage) {
            $role = Role::create(['company_id' => $company->id, 'code' => 'storage-admin', 'name' => 'Storage Admin']);
            $permission = Permission::firstOrCreate(['code' => 'storage.manage'], ['name' => 'storage.manage', 'module' => 'storage']);
            $role->permissions()->attach($permission);
            $membership = DB::table('company_user')->where('company_id', $company->id)->where('user_id', $user->id)->first();
            DB::table('company_user_role')->insert(['company_user_id' => $membership->id, 'role_id' => $role->id]);
        }

        return [$company, $user];
    }
}
