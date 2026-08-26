<?php

namespace Database\Seeders;

use App\Models\BoredPile;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\HseIncident;
use App\Models\InspectionTestPlan;
use App\Models\ItpItem;
use App\Models\JobSafetyAnalysis;
use App\Models\Nonconformity;
use App\Models\PpeIssuance;
use App\Models\Project;
use App\Models\RiskOpportunity;
use App\Models\SafetyObservation;
use App\Services\HseMetricsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo QMS & HSE (ADR-079): ITP + hold points, NCR (open/closed/overdue),
 * kalibrasi overdue, keluhan customer resolved, audit terjadwal; JSA,
 * observasi, insiden, PPE, exposure/manhours untuk FR/SR/TRIR bermakna.
 */
class DemoQmsHseSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = DemoDataSeeder::company()->id;
        $pm = DemoDataSeeder::user('pm@grahapondasi.test');
        $qms = DemoDataSeeder::user('qms@grahapondasi.test');
        $hse = DemoDataSeeder::user('hse@grahapondasi.test');
        $procurementUser = DemoDataSeeder::user('procurement@grahapondasi.test');

        $projectA = Project::where('company_id', $companyId)->where('code', 'PRJ-2601')->first();
        $projectB = Project::where('company_id', $companyId)->where('code', 'PRJ-2602')->first();

        // --- QMS risk register ---
        RiskOpportunity::firstOrCreate(['company_id' => $companyId, 'code' => 'RO-001'], [
            'project_id' => $projectA?->id, 'owner_id' => $pm->id, 'type' => 'risk',
            'title' => 'Overbreak melebihi toleransi pada lapisan pasir',
            'description' => 'Overbreak terukur hingga 12% pada zona pasir lepas; berpotensi pembengkakan volume beton.',
            'likelihood' => 3, 'impact' => 4, 'inherent_score' => 12,
            'controls' => 'Monitoring slurry, casing ekstra pada segmen pasir, verifikasi volume tiap casting.',
            'residual_likelihood' => 2, 'residual_impact' => 3, 'residual_score' => 6, 'status' => 'open',
        ]);

        // --- NCR: open + closed + supplier overdue ---
        Nonconformity::firstOrCreate(['company_id' => $companyId, 'number' => 'NCR-2026-001'], [
            'source_type' => 'inspection', 'severity' => 'major', 'project_id' => $projectA?->id,
            'description' => 'Overbreak pile proyek Cikarang terukur di atas toleransi kontrak. Perlu evaluasi metode casing dan kepadatan slurry.',
            'reported_by' => $qms->id, 'due_at' => now()->addDays(14)->toDateString(), 'status' => 'open',
        ]);
        Nonconformity::firstOrCreate(['company_id' => $companyId, 'number' => 'NCR-2026-003'], [
            'source_type' => 'audit', 'severity' => 'minor', 'project_id' => $projectA?->id,
            'description' => 'Demo seed: label lot bentonite tidak lengkap pada 2 drum di gudang site — sudah dikoreksi.',
            'reported_by' => $qms->id, 'due_at' => now()->subDays(3)->toDateString(), 'status' => 'closed',
        ]);
        Nonconformity::firstOrCreate(['company_id' => $companyId, 'number' => 'NCR-2026-004'], [
            'source_type' => 'supplier', 'severity' => 'major', 'project_id' => $projectB?->id ?? $projectA?->id,
            'description' => 'Demo seed: keterlambatan pengiriman besi vendor VEND-001 dua kali berturut-turut tanpa notifikasi.',
            'reported_by' => $procurementUser->id, 'due_at' => now()->subDays(5)->toDateString(), 'status' => 'open',
        ]);

        // --- ITP dengan hold point untuk project A ---
        if ($projectA !== null) {
            $firstPile = BoredPile::where('project_id', $projectA->id)->orderBy('pile_number')->first();
            $itp = InspectionTestPlan::firstOrCreate(
                ['company_id' => $companyId, 'number' => 'ITP-DEMO-001'],
                [
                    'project_id' => $projectA->id, 'bored_pile_id' => $firstPile?->id,
                    'title' => 'ITP Bored Pile Ø1000', 'status' => 'active',
                    'notes' => 'Demo seed: hold point setting-out & kedalaman sudah lolos.', 'prepared_by' => $qms->id,
                ]
            );
            foreach ([['Setting-out & koordinat', 'Ukur ulang total station', 'Deviasi ≤ toleransi survey company', 'hold'], ['Kedalaman akhir vs desain', 'Tape / sounding', 'Selisih dalam toleransi kedalaman', 'hold'], ['Bottom cleaning & sediment', 'Sounding + visual air', 'Sediment ≤ batas bila dikonfigurasi', 'hold'], ['QC cage sebelum diturunkan', 'Visual + ukur pitch spiral', 'Spesifikasi drawing', 'witness'], ['Slump & kuasa tekan beton', 'Cone slump + cube sample', 'Slump dalam rentang settings; kuasa ≥ fc', 'witness']] as $i => [$stage, $method, $criteria, $type]) {
                $itpItem = ItpItem::firstOrCreate(
                    ['inspection_test_plan_id' => $itp->id, 'sort_order' => $i + 1],
                    ['stage' => $stage, 'method' => $method, 'acceptance_criteria' => $criteria,
                        'checkpoint_type' => $type, 'frequency' => 'setiap pile']
                );
                if ($i < 2 && DB::table('itp_inspections')->where('itp_item_id', $itpItem->id)->doesntExist()) {
                    DB::table('itp_inspections')->insert([
                        'itp_item_id' => $itpItem->id, 'performed_at' => now()->subDays(10)->toDateString(),
                        'result' => 'pass', 'measured_value' => 'OK', 'inspector_id' => $qms->id,
                        'created_by' => $qms->id, 'notes' => 'Demo seed: hold point lolos.', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }

        // --- Kalibrasi alat ukur: satu OK satu overdue ---
        $rigId = Equipment::where('company_id', $companyId)->where('code', 'EQ-RIG-01')->value('id');
        DB::table('calibration_records')->updateOrInsert(
            ['company_id' => $companyId, 'certificate_no' => 'CAL-DEMO-001'],
            ['equipment_id' => $rigId ?? Equipment::where('company_id', $companyId)->value('id'),
                'instrument_name' => 'Caliper logging device', 'serial_number' => 'SN-CL-8823',
                'calibrated_at' => now()->subDays(80)->toDateString(), 'next_due_at' => now()->addDays(-5)->toDateString(),
                'provider' => 'PT Uji Tanah Laboratorium', 'result' => 'pass',
                'created_by' => $qms->id, 'created_at' => now(), 'updated_at' => now()]
        );

        // --- Keluhan pelanggan (resolved) ---
        DB::table('customer_complaints')->updateOrInsert(
            ['company_id' => $companyId, 'number' => 'CCM-DEMO-001'],
            ['customer_id' => Customer::where('company_id', $companyId)->where('code', 'CUST-002')->value('id'),
                'project_id' => $projectB?->id,
                'complaint_date' => now()->subDays(9)->toDateString(), 'channel' => 'email',
                'subject' => 'Laporan harian tidak konsisten', 'description' => 'Jam drilling pada laporan harian berbeda dengan log lapangan site.',
                'severity' => 'minor', 'status' => 'resolved',
                'resolution_notes' => 'Format laporan diseragamkan; supervisor diberikan training input harian.',
                'resolved_by' => $qms->id, 'resolved_at' => now()->subDays(4), 'recorded_by' => $qms->id,
                'created_at' => now(), 'updated_at' => now()]
        );

        // --- HSE ---
        JobSafetyAnalysis::firstOrCreate(['company_id' => $companyId, 'number' => 'JSA-2026-001'], [
            'project_id' => $projectA?->id, 'activity' => 'Drilling, pemasangan cage, tremie concreting',
            'location' => 'Site Cikarang - Zona Z1/Z2',
            'hazards' => ['Lubang bor runtuh', 'Tertimpa casing', 'Terjangan beton saat tremie'],
            'controls' => ['Slurry bentonite sesuai viskositas', 'Barricade zona drilling', 'PPE lengkap', 'Komunikasi radio antar operator'],
            'risk_level' => 'high', 'status' => 'active',
            'valid_from' => now()->subDays(30)->toDateString(), 'valid_until' => now()->addDays(60)->toDateString(), 'prepared_by' => $pm->id,
        ]);

        SafetyObservation::firstOrCreate(
            ['company_id' => $companyId, 'number' => 'OBS-2026-001'],
            ['project_id' => $projectA?->id, 'category' => 'unsafe_condition',
                'observed_at' => now()->subDays(6), 'location' => 'Zona drilling Z2',
                'description' => 'Demo seed: barricade zona drilling longgar setelah hujan.',
                'immediate_action' => 'Barricade dipasang ulang oleh supervisor.',
                'status' => 'resolved', 'resolved_by' => $hse->id, 'resolved_at' => now()->subDays(5),
                'resolution_notes' => 'Verifikasi harian barricade masuk checklist supervisor.', 'reported_by' => $hse->id]
        );

        HseIncident::firstOrCreate(['company_id' => $companyId, 'number' => 'INC-2026-001'], [
            'project_id' => $projectA?->id, 'type' => 'near_miss', 'severity' => 'minor',
            'occurred_at' => now()->subDays(5), 'location' => 'Yard rig utama',
            'description' => 'Sling bergeser saat pemindahan casing; tidak ada korban dan tidak ada kerusakan.',
            'immediate_action' => 'Pekerjaan crane dihentikan, inspeksi sling semua unit.',
            'status' => 'investigating', 'reported_by' => $pm->id,
        ]);

        // PPE issuance per user nyata.
        foreach ([['Operator Rig A', 'helm'], ['Operator Rig A', 'safety_shoes'], ['Welder Cage B', 'gloves']] as $i => [$person, $item]) {
            PpeIssuance::firstOrCreate(
                ['company_id' => $companyId, 'user_id' => DemoDataSeeder::user('supervisor@grahapondasi.test')->id, 'item_name' => $item, 'issued_at' => now()->subDays(25)->toDateString()],
                ['quantity' => 1, 'condition_out' => 'good', 'issued_by' => $hse->id]
            );
        }

        // Exposure manhours (nilai demo eksplisit sintetis).
        if (DB::table('hse_exposure_logs')->where('company_id', $companyId)->count() === 0) {
            foreach ([[-90, '5200.00', 42], [-60, '5400.00', 43], [-30, '5600.00', 45]] as [$offset, $manHours, $headcount]) {
                DB::table('hse_exposure_logs')->insert([
                    'company_id' => $companyId,
                    'period_month' => now()->addDays($offset)->startOfMonth()->toDateString(),
                    'man_hours' => $manHours, 'avg_headcount' => $headcount,
                    'notes' => 'Demo seed: angka manhours sintetis untuk demonstrasi FR/SR/TRIR.',
                    'created_by' => $hse->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // Metrik FR/SR/TRIR dihitung on-demand oleh HseMetricsService::summary().
    }
}
