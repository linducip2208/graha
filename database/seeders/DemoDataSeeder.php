<?php

namespace Database\Seeders;

use App\Models\BoredPile;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Enterprise Demo Dataset v2 (ADR-079) untuk PT Graha Pondasi.
 *
 * Prinsip:
 * - DETERMINISTIC: tidak ada rand(); nilai dari fixture tabel tetap.
 * - IDEMPOTENT: aman dijalankan berulang (stable unique keys / firstOrCreate).
 * - SAFE: hanya untuk lingkungan local/demo/testing; binary evidence ke disk
 *   lokal kecuali DEMO_SEED_STORAGE=true.
 *
 * Jalankan eksplisit:
 *   php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (config('app.env') === 'production') {
            $this->command?->error('DemoDataSeeder DILARANG di environment production.');

            return;
        }

        $this->command?->info('Enterprise Demo Dataset v2 — PT Graha Pondasi...');
        // Urutan penting: Finance (COA/mapping/pajak) sebelum SupplyChain (posting GR/invoice).
        foreach ([DemoOrganizationSeeder::class, DemoCommercialSeeder::class, DemoProjectSeeder::class, DemoFinanceSeeder::class] as $seeder) {
            $this->call($seeder);
        }
        $this->call(DemoFoundationSeeder::class);
        foreach ([DemoSupplyChainSeeder::class, DemoQmsHseSeeder::class, DemoDocumentSeeder::class] as $seeder) {
            $this->call($seeder);
        }

        $piles = BoredPile::count();
        $this->command?->info("Selesai. Total pile demo: {$piles}. Login: admin@grahapondasi.test / password");
    }

    /** Konteks bersama antar sub-seeder (stable keys). */
    public static function company(): Company
    {
        return Company::where('code', 'GP')->firstOrFail();
    }

    public static function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }
}
