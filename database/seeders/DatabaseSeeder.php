<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\NumberSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DatabaseSeeder = BASELINE saja (ADR-079): company, super admin, role,
 * permission, number sequence. Data DEMO TIDAK PERNAH di-seed otomatis di
 * production — hanya bila APP_ENV local/demo DAN SEED_DEMO_DATA=true.
 *
 * Catatan: sengaja TANPA WithoutModelEvents — trait HasUuid dan
 * public_uuid pile mengandalkan event `creating` agar identifier QR terisi.
 *
 * Untuk data demo eksplisit:
 *   php artisan db:seed --class=DemoDataSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBaseline();

        if ($this->shouldSeedDemo()) {
            $this->command?->info('SEED_DEMO_DATA aktif — menjalankan demo dataset...');
            $this->call(DemoDataSeeder::class);

            return;
        }

        // Pesan eksplisit agar tidak terlihat seperti "demo lupa diinstall".
        if (config('app.env') === 'production') {
            $this->command?->warn('Demo dataset DILEWATI: environment production tidak pernah otomatis di-seed data demo (ADR-079).');
        } else {
            $this->command?->warn('Demo dataset DILEWATI: set SEED_DEMO_DATA=true di .env untuk mengaktifkannya.');
        }
        $this->command?->line('Install demo sekarang:  php artisan db:seed --class=DemoDataSeeder');
        $this->command?->line('Reset penuh + demo:     php artisan demo:reset');
    }

    private function seedBaseline(): void
    {
        $company = Company::firstOrCreate(['code' => 'GP'], ['name' => 'PT Graha Pondasi', 'is_demo' => false]);
        $user = User::firstOrCreate(['email' => 'admin@grahapondasi.test'], ['name' => 'Super Admin', 'password' => 'password']);
        $company->users()->syncWithoutDetaching([$user->id => ['is_default' => true, 'is_active' => true]]);
        $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => 'super-admin'], ['name' => 'Super Admin', 'is_system' => true]);
        foreach (['organization.view', 'organization.manage', 'approval.view', 'approval.manage', 'approval.decide', 'document.view', 'document.manage', 'storage.manage', 'signature.view', 'signature.manage', 'signature.sign', 'audit.view', 'tender.view', 'tender.manage', 'contract.view', 'contract.manage', 'project.view', 'project.manage', 'inventory.view', 'inventory.manage', 'procurement.view', 'procurement.manage', 'manufacturing.view', 'manufacturing.manage', 'equipment.view', 'equipment.manage', 'finance.view', 'finance.manage', 'accounting.post', 'qms.view', 'qms.manage', 'qms.verify', 'qms.audit', 'hse.view', 'hse.manage', 'hse.verify', 'report.view', 'report.export'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => str($code)->replace('.', ' ')->title(), 'module' => str($code)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membershipId = DB::table('company_user')->where(['company_id' => $company->id, 'user_id' => $user->id])->value('id');
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membershipId, 'role_id' => $role->id]);
        NumberSequence::firstOrCreate(['company_id' => $company->id, 'document_type' => 'generic'], ['prefix' => 'GP', 'last_reset_year' => now()->year]);
        NumberSequence::firstOrCreate(['company_id' => $company->id, 'document_type' => 'tender'], ['prefix' => 'TND', 'padding' => 4, 'last_reset_year' => now()->year]);
        NumberSequence::firstOrCreate(['company_id' => $company->id, 'document_type' => 'project'], ['prefix' => 'PRJ', 'padding' => 4, 'last_reset_year' => now()->year]);
        NumberSequence::firstOrCreate(['company_id' => $company->id, 'document_type' => 'journal'], ['prefix' => 'JV', 'padding' => 5, 'last_reset_year' => now()->year]);
    }

    /**
     * Guard keamanan: demo dataset hanya boleh jalan otomatis di lingkungan
     * non-produksi dengan flag eksplisit. Production = TIDAK PERNAH.
     *
     * Flag dibaca via config (bukan env() langsung) agar deterministik saat
     * config sudah di-cache dan mudah dites.
     */
    public function shouldSeedDemo(): bool
    {
        if (config('app.env') === 'production') {
            return false;
        }

        $flag = config('app.seed_demo_data');
        if ($flag !== null && $flag !== '') {
            return filter_var($flag, FILTER_VALIDATE_BOOLEAN);
        }

        // Default: true hanya di local/demo (DX), false di testing & lainnya.
        return in_array(config('app.env'), ['local', 'demo'], true);
    }
}
