<?php

namespace App\Services;

use App\Models\BackupRecord;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BackupService
{
    public function database(?int $actorId = null, ?string $notes = null): BackupRecord
    {
        $record = BackupRecord::create(['type' => 'database', 'status' => 'running', 'disk' => 'local', 'started_at' => now(), 'created_by' => $actorId, 'notes' => $notes]);
        try {
            throw_unless(config('database.default') === 'mysql', RuntimeException::class, 'Backup database saat ini mendukung MySQL.');
            $cfg = config('database.connections.mysql');
            $relative = 'backups/database/graha-'.now()->format('Ymd-His').'-'.$record->id.'.sql.gz';
            $absolute = Storage::disk('local')->path($relative);
            if (! is_dir(dirname($absolute))) {
                mkdir(dirname($absolute), 0750, true);
            }
            $sql = substr($absolute, 0, -3);
            $command = [env('MYSQLDUMP_BINARY', 'mysqldump'), '--single-transaction', '--quick', '--skip-lock-tables', '--host='.$cfg['host'], '--port='.(string) $cfg['port'], '--user='.$cfg['username'], '--result-file='.$sql, $cfg['database']];
            $result = Process::env(['MYSQL_PWD' => (string) $cfg['password']])->timeout(1800)->run($command);
            throw_if($result->failed() || ! is_file($sql) || filesize($sql) === 0, RuntimeException::class, 'mysqldump gagal. Periksa binary dan hak akses database.');
            $in = fopen($sql, 'rb');
            $out = gzopen($absolute, 'wb9');
            while (! feof($in)) {
                gzwrite($out, fread($in, 1024 * 1024));
            }
            fclose($in);
            gzclose($out);
            unlink($sql);
            $record->update(['status' => 'completed', 'path' => $relative, 'finished_at' => now(), 'size_bytes' => filesize($absolute), 'sha256' => hash_file('sha256', $absolute)]);
        } catch (\Throwable $e) {
            $record->update(['status' => 'failed', 'finished_at' => now(), 'last_error' => $this->safeError($e)]);
        }

        return $record->refresh();
    }

    public function verify(BackupRecord $record): array
    {
        if ($record->status !== 'completed' || blank($record->path) || str_contains($record->path, '..') || ! str_starts_with($record->path, 'backups/database/')) {
            return ['valid' => false, 'message' => 'Metadata atau lokasi backup tidak valid.'];
        }
        $disk = Storage::disk($record->disk);
        if (! $disk->exists($record->path)) {
            return ['valid' => false, 'message' => 'File backup tidak ditemukan.'];
        }
        $path = $disk->path($record->path);
        if (! hash_equals((string) $record->sha256, hash_file('sha256', $path))) {
            return ['valid' => false, 'message' => 'Checksum backup tidak cocok.'];
        }
        $gz = @gzopen($path, 'rb');
        if (! $gz) {
            return ['valid' => false, 'message' => 'Arsip gzip tidak dapat dibaca.'];
        }
        $head = (string) gzread($gz, 65536);
        gzclose($gz);
        $valid = str_contains($head, 'MySQL dump') || preg_match('/\b(CREATE|INSERT|SET)\b/i', $head) === 1;

        return ['valid' => $valid, 'message' => $valid ? 'Checksum, gzip, dan header SQL valid.' : 'Isi SQL tidak dikenali sebagai dump MySQL.'];
    }

    public function prune(int $days = 14, int $keep = 3): int
    {
        $protected = BackupRecord::where('status', 'completed')->latest('finished_at')->limit(max(1, $keep))->pluck('id');
        $deleted = 0;
        BackupRecord::where('status', 'completed')->whereNotIn('id', $protected)->where('finished_at', '<', now()->subDays(max(1, $days)))->each(function ($row) use (&$deleted) {
            if ($row->path) {
                Storage::disk($row->disk)->delete($row->path);
            } $row->delete();
            $deleted++;
        });

        return $deleted;
    }

    private function safeError(\Throwable $e): string
    {
        return match (true) {
            str_contains(strtolower($e->getMessage()), 'mysqldump') => 'Database backup process gagal.', default => 'Backup gagal; periksa server log.'
        };
    }
}
