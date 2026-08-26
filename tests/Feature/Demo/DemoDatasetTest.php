<?php

namespace Tests\Feature\Demo;

use App\Models\BoredPile;
use App\Models\Company;
use App\Models\ConcreteDelivery;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Nonconformity;
use App\Models\PileReadinessCheck;
use App\Models\PileTest;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\ProjectAward;
use App\Models\Tender;
use App\Models\Vendor;
use App\Models\VendorInvoice;
use App\Services\PileRiskService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Demo dataset v2 (ADR-079): deterministik, idempotent, aman untuk production.
 */
class DemoDatasetTest extends TestCase
{
    use RefreshDatabase;

    private function runDemoSeeder(): void
    {
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder'])->assertSuccessful();
    }

    public function test_demo_seeder_runs_and_populates_all_modules(): void
    {
        $this->runDemoSeeder();

        // Foundation 60-100 pile.
        $this->assertGreaterThanOrEqual(60, BoredPile::count());
        $this->assertLessThanOrEqual(100, BoredPile::count());

        // Portfolio multi-proyek dengan marker demo.
        $this->assertGreaterThanOrEqual(3, Project::where('is_demo', true)->count());
        $this->assertSame(1, Company::where('is_demo', true)->count());

        // Modul pendukung terisi.
        $this->assertGreaterThan(0, Customer::count());
        $this->assertGreaterThanOrEqual(8, Vendor::count());
        $this->assertEquals(5, Tender::count());
        $this->assertGreaterThanOrEqual(3, ProjectAward::count());
        $this->assertGreaterThan(0, Equipment::count());
        $this->assertGreaterThan(0, Nonconformity::count());
        $this->assertGreaterThan(0, DB::table('journals')->count());
        $this->assertGreaterThan(0, DocumentCountProxy::value());
    }

    public function test_repeat_run_is_idempotent_no_duplicates(): void
    {
        $this->runDemoSeeder();
        $counts = [
            BoredPile::count(), ConcreteDelivery::count(),
            DB::table('journals')->count(), PileTest::count(),
            VendorInvoice::count(), ProgressBilling::count(),
            PileReadinessCheck::count(),
        ];
        $this->runDemoSeeder();
        $after = [
            BoredPile::count(), ConcreteDelivery::count(),
            DB::table('journals')->count(), PileTest::count(),
            VendorInvoice::count(), ProgressBilling::count(),
            PileReadinessCheck::count(),
        ];

        $this->assertSame($counts, $after);
    }

    public function test_risk_radar_demonstrates_all_levels(): void
    {
        $this->runDemoSeeder();
        $risk = app(PileRiskService::class);
        $levels = ['healthy' => 0, 'watch' => 0, 'critical' => 0];
        foreach (BoredPile::all() as $pile) {
            $levels[$risk->evaluate($pile)['level']]++;
        }

        $this->assertGreaterThan(0, $levels['healthy']);
        $this->assertGreaterThan(0, $levels['watch']);
        $this->assertGreaterThan(0, $levels['critical']);
    }

    public function test_status_distribution_is_not_uniform_and_includes_exceptions(): void
    {
        $this->runDemoSeeder();
        $statuses = BoredPile::groupBy('status')->selectRaw('status, COUNT(*) c')->pluck('c', 'status');

        $this->assertGreaterThanOrEqual(6, $statuses->count()); // banyak status berbeda
        foreach (['completed', 'planned', 'hold'] as $must) {
            $this->assertTrue($statuses->has($must), "Status {$must} harus ada di dataset demo.");
        }
    }

    public function test_finance_journals_remain_balanced(): void
    {
        $this->runDemoSeeder();
        foreach (DB::table('journals')->get(['id']) as $journal) {
            $sums = DB::table('journal_entries')->where('journal_id', $journal->id)
                ->selectRaw('COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c')->first();
            $this->assertEqualsWithDelta((float) $sums->d, (float) $sums->c, 0.001, "Journal {$journal->id} tidak balanced.");
        }
    }

    public function test_production_environment_never_auto_seeds_demo(): void
    {
        // Guard class-level.
        $seeder = new DatabaseSeeder;
        config(['app.env' => 'production']);
        $this->assertFalse($seeder->shouldSeedDemo());

        // Simulasi db:seed penuh di production → tidak ada data demo transaksi.
        config([
            'app.env' => 'production',
            'app.initial_admin_email' => 'production-admin@example.test',
            'app.initial_admin_password' => 'production-safe-password',
        ]);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder'])->assertSuccessful();
        $this->assertSame(0, BoredPile::count());
        $this->assertSame(0, Company::where('is_demo', true)->count()); // baseline murni, tanpa demo

        // Local + flag eksplisit false → juga tidak jalan.
        config(['app.env' => 'local', 'app.seed_demo_data' => false]);
        $this->assertFalse($seeder->shouldSeedDemo());

        // Flag eksplisit true → jalan.
        config(['app.seed_demo_data' => true]);
        $this->assertTrue($seeder->shouldSeedDemo());
        config(['app.seed_demo_data' => null]);
    }

    public function test_production_baseline_refuses_default_admin_credentials(): void
    {
        config([
            'app.env' => 'production',
            'app.initial_admin_email' => null,
            'app.initial_admin_password' => null,
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'admin@grahapondasi.test']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('INITIAL_ADMIN_EMAIL');

        (new DatabaseSeeder)->run();
    }

    public function test_demo_reset_command_refuses_production(): void
    {
        config(['app.env' => 'production']);
        $this->artisan('demo:reset', ['--force' => true])->expectsOutputToContain('dilarang');
    }
}

/** Proxy kecil agar test tetap ringkas saat menghitung dokumen. */
class DocumentCountProxy
{
    public static function value(): int
    {
        return DB::table('documents')->count();
    }
}
