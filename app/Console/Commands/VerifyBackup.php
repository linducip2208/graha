<?php

namespace App\Console\Commands;

use App\Models\BackupRecord;
use App\Services\BackupService;
use Illuminate\Console\Command;

class VerifyBackup extends Command
{
    protected $signature = 'backup:verify {backup? : ID metadata backup; default backup sukses terbaru}';

    protected $description = 'Verifikasi checksum, gzip integrity, dan header SQL backup terdaftar';

    public function handle(BackupService $service): int
    {
        $record = $this->argument('backup') ? BackupRecord::find($this->argument('backup')) : BackupRecord::where('status', 'completed')->latest('finished_at')->first();
        if (! $record) {
            $this->error('Backup terdaftar tidak ditemukan.');

            return self::FAILURE;
        }
        $result = $service->verify($record);
        $record->update(['verified_at' => now(), 'verification_status' => $result['valid'] ? 'passed' : 'failed']);
        $result['valid'] ? $this->info('PASS · '.$result['message']) : $this->error('FAILED · '.$result['message']);

        return $result['valid'] ? self::SUCCESS : self::FAILURE;
    }
}
