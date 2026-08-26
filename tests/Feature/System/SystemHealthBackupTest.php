<?php

namespace Tests\Feature\System;

use App\Models\BackupRecord;
use App\Models\Company;
use App\Models\SystemHeartbeat;
use App\Services\BackupService;
use App\Services\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
