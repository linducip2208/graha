<?php

namespace App\Services;

use App\Models\BackupRecord;
use App\Models\CompanyStorageProfile;
use App\Models\SystemHealthState;
use App\Models\SystemHeartbeat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SystemHealthService
{
    public function checks(int $companyId): array
    {
        $scheduler = SystemHeartbeat::find('scheduler');
        $mail = SystemHealthState::find('mail');
        $lastBackup = BackupRecord::where('status', 'completed')->latest('finished_at')->first();
        $failedJobs = DB::table('failed_jobs')->count();
        $pendingJobs = config('queue.default') === 'database' ? DB::table('jobs')->count() : null;
        $free = @disk_free_space(storage_path()) ?: null;
        $total = @disk_total_space(storage_path()) ?: null;
        $diskPercent = $free && $total ? round((1 - $free / $total) * 100, 1) : null;
        $rows = [
            $this->row('Application', 'HEALTHY', app()->environment().' · debug '.(config('app.debug') ? 'ON' : 'OFF'), config('app.debug') && app()->environment('production') ? 'CRITICAL' : null),
            $this->probe('Database', fn () => DB::select('select 1'), config('database.default')),
            $this->probe('Cache', function () {
                $key = 'health:'.uniqid();
                Cache::put($key, 'ok', 10);
                $ok = Cache::get($key) === 'ok';
                Cache::forget($key);
                if (! $ok) {
                    throw new \RuntimeException;
                }
            }, config('cache.default')),
            $this->row('Queue', $failedJobs > 0 ? 'WARNING' : 'HEALTHY', 'Driver '.config('queue.default').' · pending '.($pendingJobs ?? 'N/A').' · failed '.$failedJobs),
            $this->row('Scheduler', ! $scheduler ? 'UNKNOWN' : ($scheduler->last_seen_at->lt(now()->subMinutes(10)) ? 'CRITICAL' : 'HEALTHY'), $scheduler ? 'Heartbeat '.$scheduler->last_seen_at->diffForHumans() : 'Heartbeat belum diterima'),
            $this->row('Mail', ! $mail ? 'UNKNOWN' : strtoupper($mail->status), $mail?->message ?? 'Belum pernah diuji'),
            $this->row('Storage', is_writable(storage_path('app/private')) ? 'HEALTHY' : 'CRITICAL', 'Private filesystem '.(is_writable(storage_path('app/private')) ? 'writable' : 'not writable')),
            $this->row('Object Storage', CompanyStorageProfile::where('company_id', $companyId)->where('is_active', true)->where('last_test_status', 'CONNECTED')->exists() ? 'HEALTHY' : 'WARNING', 'Tanpa bucket scan · profile aktif '.CompanyStorageProfile::where('company_id', $companyId)->where('is_active', true)->count()),
            $this->row('Disk Usage', $diskPercent === null ? 'UNKNOWN' : ($diskPercent >= 90 ? 'CRITICAL' : ($diskPercent >= 80 ? 'WARNING' : 'HEALTHY')), $diskPercent === null ? 'Tidak dapat diukur' : $diskPercent.'% used'),
            $this->row('Backup', ! $lastBackup ? 'WARNING' : ($lastBackup->finished_at->lt(now()->subDays(2)) ? 'WARNING' : 'HEALTHY'), $lastBackup?->finished_at?->diffForHumans() ?? 'Belum ada backup sukses'),
            $this->row('Runtime', 'HEALTHY', 'PHP '.PHP_VERSION.' · Laravel '.app()->version().' · Node build '.($this->buildTimestamp() ?? 'UNKNOWN')),
        ];

        return ['checks' => $rows, 'queue' => ['driver' => config('queue.default'), 'pending' => $pendingJobs, 'failed' => $failedJobs, 'oldest_failed' => DB::table('failed_jobs')->min('failed_at')], 'last_backup' => $lastBackup, 'mail' => $mail, 'scheduler' => $scheduler];
    }

    private function probe(string $name, callable $probe, string $detail): array
    {
        try {
            $probe();

            return $this->row($name, 'HEALTHY', $detail);
        } catch (\Throwable) {
            return $this->row($name, 'CRITICAL', $detail.' tidak dapat dijangkau');
        }
    }

    private function row(string $name, string $status, string $message, ?string $override = null): array
    {
        return compact('name', 'message') + ['status' => $override ?? $status];
    }

    private function buildTimestamp(): ?string
    {
        $path = public_path('build/manifest.json');

        return is_file($path) ? date('Y-m-d H:i', filemtime($path)) : null;
    }
}
