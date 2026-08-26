<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--retention-days=14}';

    protected $description = 'Backup database MySQL ke private storage dengan retensi terkontrol';

    public function handle(BackupService $backups): int
    {
        $record = $backups->database(notes: 'Scheduled/CLI backup');
        $backups->prune((int) $this->option('retention-days'));
        $record->status === 'completed' ? $this->info('Backup berhasil: '.basename($record->path)) : $this->error($record->last_error);

        return $record->status === 'completed' ? self::SUCCESS : self::FAILURE;
    }
}
