<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProductionReadinessCheck extends Command
{
    protected $signature = 'production:check';

    protected $description = 'Memeriksa konfigurasi dan dependency minimum sebelum deployment produksi';

    public function handle(): int
    {
        $checks = [
            ['Environment production', app()->environment('production'), app()->environment()],
            ['Debug nonaktif', ! config('app.debug'), config('app.debug') ? 'APP_DEBUG=true' : 'false'],
            ['URL HTTPS', str_starts_with((string) config('app.url'), 'https://'), (string) config('app.url')],
            ['APP_KEY tersedia', filled(config('app.key')), filled(config('app.key')) ? 'tersedia' : 'kosong'],
            ['Database MySQL', config('database.default') === 'mysql', (string) config('database.default')],
            ['Queue asynchronous', ! in_array(config('queue.default'), ['sync', 'null'], true), (string) config('queue.default')],
            ['Mail bukan log/array', ! in_array(config('mail.default'), ['log', 'array'], true), (string) config('mail.default')],
            ['Session terenkripsi', (bool) config('session.encrypt'), config('session.encrypt') ? 'aktif' : 'nonaktif'],
            ['Cookie secure', (bool) config('session.secure'), config('session.secure') ? 'aktif' : 'nonaktif'],
            ['Storage writable', is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')), storage_path()],
        ];

        try {
            DB::select('select 1');
            $checks[] = ['Koneksi database', true, 'terhubung'];
            $checks[] = ['Tabel migrations', Schema::hasTable('migrations'), Schema::hasTable('migrations') ? 'tersedia' : 'tidak tersedia'];
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles(database_path('migrations'));
            $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());
            $checks[] = ['Migration pending', $pending === [], $pending === [] ? 'tidak ada' : implode(', ', $pending)];
        } catch (Throwable $exception) {
            $checks[] = ['Koneksi database', false, $exception->getMessage()];
        }

        $this->table(['Pemeriksaan', 'Status', 'Detail'], array_map(fn (array $check) => [$check[0], $check[1] ? 'LULUS' : 'GAGAL', $check[2]], $checks));
        $failed = collect($checks)->where(1, false)->count();
        if ($failed > 0) {
            $this->error("Production readiness GAGAL: {$failed} pemeriksaan belum terpenuhi.");

            return self::FAILURE;
        }

        $this->info('Production readiness konfigurasi dasar LULUS. Tetap wajib UAT, backup restore drill, security review, dan load test.');

        return self::SUCCESS;
    }
}
