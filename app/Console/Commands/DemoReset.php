<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Reset penuh database lalu re-seed DEMO dataset. DILARANG di production —
 * perintah ini DESTRUKTIF (migrate:fresh). Guard ganda environment + konfirmasi.
 */
class DemoReset extends Command
{
    protected $signature = 'demo:reset {--force : Lewati konfirmasi}';

    protected $description = 'Reset database dan isi ulang dengan demo dataset (hanya local/demo/testing)';

    public function handle(): int
    {
        if (config('app.env') === 'production') {
            $this->error('demo:reset dilarang di environment production.');

            return self::FAILURE;
        }
        if (! in_array(config('app.env'), ['local', 'demo', 'testing'], true)) {
            $this->error('demo:reset hanya untuk environment local/demo/testing.');

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm('Semua data akan DIHAPUS dan diganti dataset demo. Lanjutkan?')) {
            $this->line('Dibatalkan.');

            return self::SUCCESS;
        }

        $this->call('migrate:fresh');
        $this->call('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder']);
        $this->info('Demo environment siap. Login: admin@grahapondasi.test / password');

        return self::SUCCESS;
    }
}
