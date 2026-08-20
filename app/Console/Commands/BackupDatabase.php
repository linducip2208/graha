<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--retention-days=14}';

    protected $description = 'Backup database MySQL ke private storage dengan retensi terkontrol';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->error('Backup command ini hanya mendukung koneksi MySQL.');

            return self::FAILURE;
        }
        $connection = config('database.connections.mysql');
        $directory = storage_path('app/private/backups/database');
        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
        $file = $directory.'/grahapondasi-'.now()->format('Ymd-His').'.sql';
        $command = [env('MYSQLDUMP_BINARY', 'mysqldump'), '--single-transaction', '--quick', '--skip-lock-tables', '--host='.$connection['host'], '--port='.(string) $connection['port'], '--user='.$connection['username'], '--result-file='.$file, $connection['database']];
        $result = Process::env(['MYSQL_PWD' => (string) $connection['password']])->timeout(1800)->run($command);
        if ($result->failed() || ! is_file($file) || filesize($file) === 0) {
            if (is_file($file)) {
                unlink($file);
            }
            $this->error('Backup gagal. Periksa binary mysqldump dan log server.');

            return self::FAILURE;
        }
        $cutoff = now()->subDays(max(1, (int) $this->option('retention-days')))->timestamp;
        foreach (Storage::disk('local')->files('backups/database') as $old) {
            if (Storage::disk('local')->lastModified($old) < $cutoff) {
                Storage::disk('local')->delete($old);
            }
        }
        $this->info('Backup berhasil: '.basename($file));

        return self::SUCCESS;
    }
}
