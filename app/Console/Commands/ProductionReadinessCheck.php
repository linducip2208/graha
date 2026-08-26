<?php

namespace App\Console\Commands;

use App\Models\BackupRecord;
use App\Models\CompanyStorageProfile;
use App\Models\SystemHealthState;
use App\Models\SystemHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ProductionReadinessCheck extends Command
{
    protected $signature = 'production:check';

    protected $description = 'Read-only production configuration and operational dependency gate';

    public function handle(): int
    {
        $checks = [];
        $add = function (string $name, string $status, string $detail) use (&$checks) {
            $checks[] = compact('name', 'status', 'detail');
        };
        $add('Environment', app()->environment('production') ? 'PASS' : 'FAIL', app()->environment());
        $add('Debug disabled', config('app.debug') ? 'FAIL' : 'PASS', config('app.debug') ? 'APP_DEBUG=true' : 'false');
        $key = (string) config('app.key');
        $decodedKey = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        $add('APP_KEY', is_string($decodedKey) && strlen($decodedKey) >= 32 ? 'PASS' : 'FAIL', filled($key) ? 'configured' : 'missing');
        $url = (string) config('app.url');
        $add('APP_URL HTTPS', str_starts_with($url, 'https://') ? 'PASS' : 'FAIL', parse_url($url, PHP_URL_HOST) ?: 'invalid');
        $add('Database driver', config('database.default') === 'mysql' ? 'PASS' : 'FAIL', (string) config('database.default'));
        $add('Queue async', in_array(config('queue.default'), ['sync', 'null'], true) ? 'FAIL' : 'PASS', (string) config('queue.default'));
        $add('Session secure/encrypted', config('session.secure') && config('session.encrypt') ? 'PASS' : 'FAIL', 'secure='.(int) config('session.secure').' encrypted='.(int) config('session.encrypt'));
        $add('Session backend', config('session.driver') === 'database' ? 'PASS' : 'WARNING', (string) config('session.driver'));
        $add('Mail transport', in_array(config('mail.default'), ['log', 'array'], true) ? 'WARNING' : 'PASS', (string) config('mail.default'));
        $add('Writable paths', is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')) ? 'PASS' : 'FAIL', 'storage + bootstrap/cache');
        $demoEnabled = filter_var(config('app.seed_demo_data', false), FILTER_VALIDATE_BOOL);
        $add('Demo seeding disabled', $demoEnabled ? 'FAIL' : 'PASS', 'SEED_DEMO_DATA='.($demoEnabled ? 'true' : 'false'));
        try {
            DB::select('select 1');
            $add('Database connection', 'PASS', 'connected');
            $defaultPasswordFound = false;
            if (Schema::hasTable('users')) {
                DB::table('users')->select(['id', 'password'])->orderBy('id')->chunkById(200, function ($users) use (&$defaultPasswordFound) {
                    foreach ($users as $user) {
                        if (Hash::check('password', $user->password)) {
                            $defaultPasswordFound = true;

                            return false;
                        }
                    }

                    return true;
                });
            }
            $add('Default passwords', $defaultPasswordFound ? 'FAIL' : 'PASS', $defaultPasswordFound ? 'known demo password detected' : 'not detected');
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles(database_path('migrations'));
            $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());
            $add('Migration status', $pending === [] ? 'PASS' : 'FAIL', $pending === [] ? 'up to date' : count($pending).' pending');
            $probe = 'production-check:'.uniqid();
            Cache::put($probe, 'ok', 10);
            $cacheOk = Cache::get($probe) === 'ok';
            Cache::forget($probe);
            $add('Cache', $cacheOk ? 'PASS' : 'FAIL', (string) config('cache.default'));
            $heartbeat = Schema::hasTable('system_heartbeats') ? SystemHeartbeat::find('scheduler') : null;
            $add('Scheduler heartbeat', $heartbeat && $heartbeat->last_seen_at->gte(now()->subMinutes(10)) ? 'PASS' : 'FAIL', $heartbeat?->last_seen_at?->toIso8601String() ?? 'missing');
            $backup = Schema::hasTable('backup_records') ? BackupRecord::where('status', 'completed')->latest('finished_at')->first() : null;
            $add('Backup freshness', $backup && $backup->finished_at->gte(now()->subDays(2)) ? 'PASS' : 'FAIL', $backup?->finished_at?->toIso8601String() ?? 'missing');
            $verified = Schema::hasTable('backup_records') ? BackupRecord::where('verification_status', 'passed')->latest('verified_at')->first() : null;
            $add('Verified backup', $verified ? 'PASS' : 'FAIL', $verified?->verified_at?->toIso8601String() ?? 'missing');
            $mail = Schema::hasTable('system_health_states') ? SystemHealthState::find('mail') : null;
            $add('Mail test', $mail?->status === 'healthy' && $mail->last_tested_at?->gte(now()->subDays(30)) ? 'PASS' : 'WARNING', $mail?->last_tested_at?->toIso8601String() ?? 'not tested');
            $profiles = Schema::hasTable('company_storage_profiles') ? CompanyStorageProfile::where('is_active', true)->count() : 0;
            $failedProfiles = $profiles ? CompanyStorageProfile::where('is_active', true)
                ->where(fn ($query) => $query->whereNull('last_test_status')->orWhere('last_test_status', '!=', 'CONNECTED'))
                ->count() : 0;
            $add('Object storage profiles', $failedProfiles ? 'FAIL' : ($profiles ? 'PASS' : 'WARNING'), $profiles.' active - '.$failedProfiles.' unhealthy');
        } catch (\Throwable $e) {
            $add('Runtime dependency probe', 'FAIL', 'probe failed · reference '.substr(hash('sha256', $e::class), 0, 10));
        }
        $this->table(['Check', 'Status', 'Detail'], array_map(fn ($row) => [$row['name'], $row['status'], $row['detail']], $checks));
        $fails = collect($checks)->where('status', 'FAIL')->count();
        $warnings = collect($checks)->where('status', 'WARNING')->count();
        $this->line('PASS '.collect($checks)->where('status', 'PASS')->count()." · WARNING {$warnings} · FAIL {$fails}");
        if ($fails) {
            $this->error('NOT READY · release blocker masih ada.');

            return self::FAILURE;
        }
        $this->warn($warnings ? 'READY WITH CONDITIONS · operator tasks/warnings belum ditutup.' : 'Configuration gate PASS; UAT dan DR rehearsal tetap gate eksternal.');

        return self::SUCCESS;
    }
}
