<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo portofolio proyek (ADR-079):
 * - PRJ-2601 Healthy (in progress) — Tower Cikarang.
 * - PRJ-2602 At Risk (delay & overbreak tinggi) — Gudang Karawang.
 * - PRJ-2603 Near Completion — Jembatan Akses Bekasi.
 * - PRJ-2604 Planning — Depot BBM Subang.
 */
class DemoProjectSeeder extends Seeder
{
    public const PROJECTS = [
        ['code' => 'PRJ-2601', 'name' => 'Fondasi Bored Pile Tower Transmisi Cikarang', 'customer' => 'CUST-001',
            'contract_value' => '2500000000', 'estimated_cost' => '1900000000', 'status' => 'in_progress',
            'start_offset' => -90, 'end_offset' => 60, 'health_note' => 'healthy'],
        ['code' => 'PRJ-2602', 'name' => 'Pondasi Bored Pile Gudang Logistik Karawang', 'customer' => 'CUST-002',
            'contract_value' => '1800000000', 'estimated_cost' => '1650000000', 'status' => 'in_progress',
            'start_offset' => -120, 'end_offset' => -10, 'health_note' => 'at_risk'],
        ['code' => 'PRJ-2603', 'name' => 'Bored Pile Jembatan Akses Bekasi', 'customer' => 'CUST-004',
            'contract_value' => '2900000000', 'estimated_cost' => '2400000000', 'status' => 'in_progress',
            'start_offset' => -150, 'end_offset' => 15, 'health_note' => 'near_completion'],
        ['code' => 'PRJ-2604', 'name' => 'Pondasi Depot BBM Subang', 'customer' => 'CUST-003',
            'contract_value' => '2100000000', 'estimated_cost' => '1750000000', 'status' => 'planning',
            'start_offset' => 30, 'end_offset' => 240, 'health_note' => 'planning'],
    ];

    public const ZONES = [
        'PRJ-2601' => [['Z1', 'Zona Menara 1-6'], ['Z2', 'Zona Menara 7-12']],
        'PRJ-2602' => [['ZA', 'Zona Gudang A'], ['ZB', 'Zona Loading Bay']],
        'PRJ-2603' => [['ABUT-A', 'Abutment A'], ['ABUT-B', 'Abutment B'], ['PIER', 'Pier Tengah']],
        'PRJ-2604' => [['TANK-1', 'Area Tangki 1'], ['TANK-2', 'Area Tangki 2']],
    ];

    public function run(): void
    {
        $companyId = DemoDataSeeder::company()->id;
        $pm = DemoDataSeeder::user('pm@grahapondasi.test');

        foreach (self::PROJECTS as $spec) {
            $customer = Customer::where('company_id', $companyId)->where('code', $spec['customer'])->firstOrFail();
            $project = Project::updateOrCreate(
                ['company_id' => $companyId, 'code' => $spec['code']],
                [
                    'name' => $spec['name'], 'is_demo' => true,
                    'customer_id' => $customer->id,
                    'contract_value' => $spec['contract_value'], 'estimated_cost' => $spec['estimated_cost'],
                    'planned_start' => now()->addDays($spec['start_offset'])->toDateString(),
                    'planned_end' => now()->addDays($spec['end_offset'])->toDateString(),
                    'overbreak_tolerance_percent' => $spec['health_note'] === 'at_risk' ? '8' : '10',
                    'status' => $spec['status'],
                ]
            );
            foreach (self::ZONES[$spec['code']] as [$zoneCode, $zoneName]) {
                $project->zones()->firstOrCreate(['code' => $zoneCode], ['name' => $zoneName]);
            }
            // WBS & cost code dasar per proyek.
            DB::table('project_wbs')->updateOrInsert(
                ['project_id' => $project->id, 'code' => 'WBS-01'],
                ['name' => 'Pekerjaan Pondasi', 'budget' => $spec['estimated_cost'], 'updated_at' => now(), 'created_at' => now()]
            );
            foreach ([['CC-MAT', 'Material Pondasi', 'material'], ['CC-EQP', 'Equipment & Rig', 'equipment'], ['CC-SUB', 'Subkon Pengujian', 'subcontract']] as [$cc, $ccName, $category]) {
                DB::table('project_cost_codes')->updateOrInsert(
                    ['project_id' => $project->id, 'code' => $cc],
                    ['name' => $ccName, 'category' => $category, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }
    }
}
