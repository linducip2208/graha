<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\NumberSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $company = Company::firstOrCreate(['code' => 'GP'], ['name' => 'PT Graha Pondasi']);
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
        NumberSequence::firstOrCreate(['company_id' => $company->id, 'document_type' => 'tender'], ['prefix' => 'TND', 'last_reset_year' => now()->year]);
        NumberSequence::firstOrCreate(['company_id' => $company->id, 'document_type' => 'project'], ['prefix' => 'PRJ', 'last_reset_year' => now()->year]);
        NumberSequence::firstOrCreate(['company_id' => $company->id, 'document_type' => 'journal'], ['prefix' => 'JV', 'padding' => 5, 'last_reset_year' => now()->year]);

        $this->call(DemoDataSeeder::class);
    }
}
