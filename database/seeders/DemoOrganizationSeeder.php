<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Demo organisasi (ADR-079): 12 user per role + cabang + departemen. */
class DemoOrganizationSeeder extends Seeder
{
    public const DEMO_USERS = [
        'admin@grahapondasi.test' => ['Super Admin', null],
        'direktur@grahapondasi.test' => ['Direktur Operasi', 'director'],
        'pm@grahapondasi.test' => ['Project Manager', 'project-manager'],
        'supervisor@grahapondasi.test' => ['Site Supervisor', 'site-supervisor'],
        'finance@grahapondasi.test' => ['Finance Manager', 'finance-manager'],
        'accountant@grahapondasi.test' => ['Accountant', 'accountant'],
        'treasury@grahapondasi.test' => ['Treasury', 'treasury'],
        'procurement@grahapondasi.test' => ['Procurement Officer', 'procurement-officer'],
        'warehouse@grahapondasi.test' => ['Warehouse Officer', 'warehouse'],
        'qms@grahapondasi.test' => ['QMS Engineer', 'qms-engineer'],
        'hse@grahapondasi.test' => ['HSE Officer', 'hse-officer'],
        'document.controller@grahapondasi.test' => ['Document Controller', 'document-controller'],
    ];

    public function run(): void
    {
        $company = Company::firstOrCreate(['code' => 'GP'], ['name' => 'PT Graha Pondasi']);
        if (! $company->is_demo) {
            $company->update(['is_demo' => true]);
        }

        foreach (self::DEMO_USERS as $email => [$name, $roleCode]) {
            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'password']);
            $company->users()->syncWithoutDetaching([$user->id => ['is_default' => true, 'is_active' => true]]);
            if ($roleCode !== null) {
                $this->attachRole($company, $user, $roleCode, $name, $this->permissionsFor($roleCode));
            }
        }

        // Cabang & departemen.
        Branch::firstOrCreate(['company_id' => $company->id, 'code' => 'HO-JKT'], ['name' => 'Head Office Jakarta']);
        Branch::firstOrCreate(['company_id' => $company->id, 'code' => 'SITE-CKG'], ['name' => 'Site Cikarang']);
        foreach ([['ENG', 'Engineering'], ['OPS', 'Operasional'], ['FIN', 'Keuangan'], ['SCM', 'Supply Chain'], ['QAQC', 'QA/QC & HSE']] as [$code, $name]) {
            DB::table('departments')->updateOrInsert(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    private function attachRole(Company $company, User $user, string $code, string $name, array $permissions): void
    {
        $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => $code], ['name' => $name, 'is_system' => false]);
        foreach ($permissions as $permCode) {
            $permission = Permission::firstOrCreate(['code' => $permCode], ['name' => str($permCode)->replace('.', ' ')->title(), 'module' => str($permCode)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $pivotId = DB::table('company_user')->where('company_id', $company->id)->where('user_id', $user->id)->value('id');
        if ($pivotId) {
            DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $pivotId, 'role_id' => $role->id]);
        }
    }

    private function permissionsFor(string $role): array
    {
        return match ($role) {
            'director' => ['approval.view', 'approval.decide', 'tender.view', 'tender.manage', 'project.view', 'finance.view', 'report.view'],
            'project-manager' => ['project.view', 'project.manage', 'inventory.view', 'equipment.view', 'equipment.manage', 'hse.view', 'report.view'],
            'site-supervisor' => ['project.view', 'project.manage'],
            'finance-manager', 'accountant' => ['finance.view', 'finance.manage', 'report.view'],
            'treasury' => ['finance.view', 'accounting.post'],
            'procurement-officer' => ['procurement.view', 'procurement.manage', 'inventory.view', 'inventory.manage'],
            'warehouse' => ['inventory.view', 'inventory.manage'],
            'qms-engineer' => ['qms.view', 'qms.manage', 'qms.verify', 'project.view'],
            'hse-officer' => ['hse.view', 'hse.manage', 'project.view'],
            'document-controller' => ['document.view', 'document.manage', 'storage.manage'],
            default => [],
        };
    }
}
