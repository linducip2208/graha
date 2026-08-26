<?php

namespace Database\Seeders;

use App\Models\BoredPile;
use App\Models\BoredPileDrilling;
use App\Models\BoredPileDrillingLayer;
use App\Models\CompanySetting;
use App\Models\ConcreteDelivery;
use App\Models\Equipment;
use App\Models\Nonconformity;
use App\Models\PileBottomCleaningInspection;
use App\Models\PileConcretePourInterval;
use App\Models\PileReadinessCheck;
use App\Models\PileTest;
use App\Models\PileTremieLog;
use App\Models\Project;
use App\Models\ReinforcementCage;
use App\Models\SlurryTest;
use App\Models\StoredFile;
use App\Models\Vendor;
use App\Services\BoredPileService;
use App\Services\PileReadinessService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo foundation data (ADR-079): 64 pile lintas 4 proyek dengan distribusi
 * status bermakna + field records nyata-terlihat (bore log, delivery, slurry,
 * tremie, cleaning, pour interval) + 9 skenario risiko terkontrol untuk
 * Risk Radar. Semua nilai deterministik dari fixture.
 */
class DemoFoundationSeeder extends Seeder
{
    private const CHAIN = ['planned', 'setting_out', 'drilling', 'cleaning', 'inspection', 'cage_installation', 'casting', 'testing', 'completed'];

    /** Distribusi status per proyek: [jumlah, status] — total 64 pile. */
    private const PLAN = [
        'PRJ-2601' => [['A', 6, 'completed'], ['A', 3, 'testing'], ['A', 2, 'casting'], ['A', 2, 'cage_installation'], ['A', 2, 'inspection'], ['A', 2, 'drilling'], ['A', 1, 'cleaning'], ['A', 1, 'setting_out'], ['A', 1, 'planned']],
        'PRJ-2602' => [['B', 4, 'completed'], ['B', 2, 'testing'], ['B', 1, 'casting'], ['B', 1, 'cage_installation'], ['B', 2, 'inspection'], ['B', 2, 'drilling'], ['B', 1, 'rework'], ['B', 2, 'hold'], ['B', 1, 'rejected'], ['B', 2, 'planned']],
        'PRJ-2603' => [['C', 9, 'completed'], ['C', 3, 'testing'], ['C', 2, 'casting'], ['C', 1, 'cage_installation'], ['C', 1, 'inspection']],
        'PRJ-2604' => [['D', 10, 'planned']],
    ];

    public function run(): void
    {
        $companyId = DemoDataSeeder::company()->id;
        $pm = DemoDataSeeder::user('pm@grahapondasi.test');
        $supervisor = DemoDataSeeder::user('supervisor@grahapondasi.test');
        $qms = DemoDataSeeder::user('qms@grahapondasi.test');

        CompanySetting::put($companyId, [
            'slurry_policy_enabled' => '1',
            'slurry_density_min' => '1.05', 'slurry_density_max' => '1.25',
            'slurry_viscosity_min' => '30', 'slurry_viscosity_max' => '60',
            'slurry_sand_content_max' => '6',
            'tremie_log_enabled' => '1', 'tremie_max_embedment_m' => '',
            'require_cleaning_inspection' => '0',
        ]);

        $rigs = Equipment::where('company_id', $companyId)->where('category', 'rig')->orderBy('code')->get();
        $pileService = app(BoredPileService::class);
        $readiness = app(PileReadinessService::class);

        foreach (self::PLAN as $projectCode => $groups) {
            $project = Project::where('company_id', $companyId)->where('code', $projectCode)->firstOrFail();
            $zones = $project->zones()->get()->keyBy('code');
            $wbsId = DB::table('project_wbs')->where('project_id', $project->id)->where('code', 'WBS-01')->value('id');
            $costCodeId = DB::table('project_cost_codes')->where('project_id', $project->id)->where('code', 'CC-MAT')->value('id');
            $seq = 0;

            foreach ($groups as [$zonePrefix, $count, $targetStatus]) {
                for ($i = 1; $i <= $count; $i++) {
                    $seq++;
                    $pileNumber = sprintf('BP-%s%02d', $zonePrefix, $seq);
                    [$diameter, $depth] = $this->designFor($seq);
                    $statusIndex = array_search($targetStatus === 'rework' ? 'rework' : ($targetStatus === 'hold' ? 'hold' : ($targetStatus === 'rejected' ? 'rejected' : $targetStatus)), ['planned', 'setting_out', 'drilling', 'cleaning', 'inspection', 'cage_installation', 'casting', 'testing', 'completed'], true);
                    if ($targetStatus === 'rework') {
                        $statusIndex = 8; // sempat completed lalu rework
                    }
                    if ($targetStatus === 'rejected') {
                        $statusIndex = 7;
                    }

                    $pile = BoredPile::firstOrCreate(
                        ['project_id' => $project->id, 'pile_number' => $pileNumber],
                        [
                            'project_zone_id' => $zones[$zonePrefix]->id ?? $zones->first()->id,
                            'project_wbs_id' => $wbsId, 'project_cost_code_id' => $costCodeId,
                            'diameter_mm' => (string) $diameter, 'planned_depth_m' => $depth,
                            'concrete_grade' => "fc'25", 'drilling_method' => 'rotary_bentonite',
                            'slurry_type' => $projectCode === 'PRJ-2602' ? 'bentonite' : null,
                            'operator_name' => 'Operator '.chr(64 + (($seq % 3) + 1)),
                            'supervisor_name' => $supervisor->name,
                            'grid_reference' => sprintf('%s-%02d', $zonePrefix, $seq),
                            'coordinate_x' => number_format(500 + $seq * 7.5, 4, '.', ''),
                            'coordinate_y' => number_format(1200 + $seq * 5.25, 4, '.', ''),
                            'latitude' => -6.15 + $seq * 0.0008,
                            'longitude' => 107.02 + $seq * 0.0011,
                            'design_easting' => number_format(500 + $seq * 7.5, 4, '.', ''),
                            'design_northing' => number_format(1200 + $seq * 5.25, 4, '.', ''),
                            'actual_easting' => number_format(500 + $seq * 7.5 + ($seq % 4) * 0.01, 4, '.', ''),
                            'actual_northing' => number_format(1200 + $seq * 5.25 - ($seq % 3) * 0.008, 4, '.', ''),
                            'design_top_elevation' => '12.000', 'actual_top_elevation' => '11.995',
                            'platform_ready_at' => in_array($targetStatus, ['planned'], true) ? null : now()->subDays(85),
                            'concrete_booking_confirmed_at' => in_array($targetStatus, ['casting', 'testing', 'completed', 'rework', 'rejected']) ? now()->subDays(40) : null,
                            'rig_equipment_id' => in_array($targetStatus, ['planned', 'setting_out'], true) ? null : ($rigs[$seq % max(1, $rigs->count())]->id ?? null),
                            'planned_date' => $targetStatus === 'planned' ? now()->addDays(($seq % 6) + 1)->toDateString() : now()->subDays(max(1, 80 - $seq))->toDateString(),
                            'created_by' => $pm->id, 'status' => 'planned',
                        ]
                    );

                    // Transisi status via service nyata sampai target (sekali saja).
                    $activityCount = $pile->activities()->count();
                    if ($activityCount === 0 && ! in_array($targetStatus, ['planned'], true)) {
                        if ($targetStatus === 'hold') {
                            $pileService->transition($pile, 'setting_out', $supervisor, 'Demo seed: setting-out.');
                            $pileService->transition($pile->refresh(), 'hold', DemoDataSeeder::user('direktur@grahapondasi.test'), 'Demo seed: menunggu izin client.');
                        } else {
                            // rework/rejected dicapai dari testing (TRANSITIONS testing => [.., rework, rejected]).
                            $chainTarget = in_array($targetStatus, ['rework', 'rejected'], true) ? 'testing' : $targetStatus;
                            foreach (array_slice(self::CHAIN, 1, (int) array_search($chainTarget, self::CHAIN, true)) as $step) {
                                if ($pile->refresh()->status !== $step && in_array($step, BoredPileService::TRANSITIONS[$pile->status] ?? [], true)) {
                                    try {
                                        $pileService->transition($pile, $step, $supervisor, 'Demo seed: progres awal.');
                                    } catch (\Throwable) {
                                        break; // gate belum lolos → berhenti di status terakhir yang sah.
                                    }
                                }
                            }
                            if ($targetStatus === 'rework') {
                                $pile->update(['rework_reason' => 'Demo seed: kualitas beton segmen 12-16 m tidak sesuai — perbaikan redrilling parsial.']);
                                try {
                                    $pileService->transition($pile->refresh(), 'rework', $supervisor, 'Demo seed: rework.');
                                } catch (\Throwable) {
                                }
                            } elseif ($targetStatus === 'rejected') {
                                $pile->update(['rejection_reason' => 'Demo seed: uji PIT gagal dua kali — pile dibatalkan dan diganti lokasi baru.']);
                                try {
                                    $pileService->transition($pile->refresh(), 'rejected', DemoDataSeeder::user('direktur@grahapondasi.test'), 'Demo seed: rejected.');
                                } catch (\Throwable) {
                                }
                            }
                        }
                    }

                    $this->seedFieldData($pile->refresh(), $targetStatus, $seq, $pm, $supervisor, $qms);
                }
            }
        }

        // Skenario risiko terkontrol (P31) — setelah semua pile & data ada.
        $this->applyRiskScenarios();

        // Snapshot readiness sekali per pile (idempotent).
        foreach (BoredPile::whereIn('project_id', Project::where('company_id', $companyId)->pluck('id'))->get() as $pile) {
            if (! PileReadinessCheck::where('bored_pile_id', $pile->id)->exists()) {
                $readiness->recordCheck($pile, PileReadinessCheck::KIND_DRILL, $pm);
                if (in_array($pile->status, ['casting', 'testing', 'completed', 'rework', 'rejected'], true)) {
                    $readiness->recordCheck($pile, PileReadinessCheck::KIND_CAST, $qms);
                }
            }
        }
    }

    /** Desain deterministik: variasi diameter/kedalaman dari nomor urut. */
    private function designFor(int $seq): array
    {
        return match ($seq % 4) {
            0 => [1200, '26.500'],
            1 => [800, '22.500'],
            2 => [1000, '24.000'],
            default => [1000, '20.000'],
        };
    }

    /** Field records per pile: drilling, layers, cleaning, slurry, tremie, delivery, pour interval. */
    private function seedFieldData(BoredPile $pile, string $targetStatus, int $seq, $pm, $supervisor, $qms): void
    {
        $inExecution = ! in_array($targetStatus, ['planned', 'setting_out', 'hold'], true);
        if (! $inExecution || BoredPileDrilling::where('bored_pile_id', $pile->id)->exists()) {
            return;
        }

        $startAt = now()->subDays(max(2, 78 - $seq))->setTime(8, 0);
        $finishAt = $startAt->copy()->addHours(6 + ($seq % 5));
        $drilling = BoredPileDrilling::create([
            'company_id' => $pile->project->company_id, 'bored_pile_id' => $pile->id,
            'drilling_started_at' => $startAt, 'drilling_finished_at' => $finishAt,
            'groundwater_level_m' => '2.400', 'drilling_tool' => $seq % 2 === 0 ? 'Bucket' : 'Auger',
            'cleaning_method' => 'airlift', 'final_cleaning_minutes' => 35, 'sediment_depth_mm' => '25',
            'weather' => 'cerah', 'notes' => 'DEMO / SAMPLE — catatan lapangan sintetis.',
            'recorded_by' => $supervisor->id, 'status' => 'verified', 'verified_by' => $qms->id, 'verified_at' => $finishAt->addHours(2),
        ]);
        foreach ([['0', '3.5', 'Lempung liat cokelat keras'], ['3.5', '11', 'Pasir lepas abu-abu, air tinggi'], ['11', (string) ((float) $pile->planned_depth_m + 0.5), 'Breksi tuf sedang-keras']] as $i => [$from, $to, $desc]) {
            BoredPileDrillingLayer::create([
                'bored_pile_drilling_id' => $drilling->id, 'sequence' => $i + 1,
                'depth_from_m' => $from, 'depth_to_m' => $to, 'soil_description' => $desc,
            ]);
        }
        if ($pile->actual_depth_m === null) {
            $pile->update(['actual_depth_m' => bcsub((string) $pile->planned_depth_m, '0.'.(100 - $seq % 90), 3)]);
        }

        // Cleaning inspection accepted (gate opsional tetap OFF di settings).
        PileBottomCleaningInspection::firstOrCreate(
            ['bored_pile_id' => $pile->id, 'inspected_at' => $finishAt->addHours(3)],
            ['company_id' => $pile->project->company_id, 'method' => 'airlift', 'sediment_thickness_mm' => '18',
                'cleaned_at' => $finishAt->addHours(1), 'inspected_by' => $qms->id, 'witnessed_by' => $supervisor->id,
                'status' => 'accepted', 'notes' => 'Dasar lubang bersih, air jernih.']
        );

        // Slurry tests (proyek at-risk memakai bentonite; lainnya polymer).
        $slurryType = $pile->slurry_type ?? 'polymer';
        foreach ([['before_drilling', '1.08', '42', '9.8', '2.5'], ['before_casting', '1.12', '38', '9.5', '4.8']] as $phaseIdx => [$phaseName, $density, $viscosity, $ph, $sand]) {
            SlurryTest::firstOrCreate(
                ['bored_pile_id' => $pile->id, 'phase' => $phaseName],
                [
                    'company_id' => $pile->project->company_id, 'type' => $slurryType, 'tested_at' => $finishAt->addHours($phaseIdx + 1),
                    'batch_number' => 'SLB-'.substr($pile->pile_number, -3).'-0'.($phaseIdx + 1),
                    'density' => $density, 'viscosity' => $viscosity, 'ph' => $ph, 'sand_content_percent' => $sand,
                    'temperature' => '29.5', 'sampled_by' => $supervisor->id, 'verified_by' => $qms->id,
                    'verified_at' => $finishAt->addHours(4), 'status' => 'accepted',
                ]
            );
        }

        // Tremie logs 3 segmen.
        foreach ([[24.0, 19.8], [23.0, 19.2], [22.0, 17.4]] as $tIdx => [$totalLength, $tipDepth]) {
            PileTremieLog::create([
                'company_id' => $pile->project->company_id, 'bored_pile_id' => $pile->id,
                'sequence' => $tIdx + 1, 'recorded_at' => $finishAt->addHours(5 + $tIdx),
                'tremie_total_length_m' => number_format($totalLength, 2, '.', ''),
                'tremie_tip_depth_m' => number_format($tipDepth, 2, '.', ''),
                'concrete_level_m' => number_format($tipDepth - 2.5, 2, '.', ''),
                'embedment_m' => number_format($totalLength - $tipDepth, 2, '.', ''),
                'flag' => 'normal', 'recorded_by' => $supervisor->id,
            ]);
        }

        // Concrete deliveries 3 truck (gap normal 55 menit).
        $volumePerTruck = bcdiv((string) $this->expectedConcrete($pile), '3', 4);
        foreach ([0, 1, 2] as $truckIdx) {
            $arrival = $finishAt->addHours(6)->addMinutes($truckIdx * 55);
            ConcreteDelivery::firstOrCreate(
                ['company_id' => $pile->project->company_id, 'delivery_order_number' => 'DO-DEMO-'.substr($pile->pile_number, -3).'-0'.($truckIdx + 1)],
                [
                    'project_id' => $pile->project->id, 'bored_pile_id' => $pile->id, 'sequence' => $truckIdx + 1,
                    'vendor_id' => Vendor::where('company_id', $pile->project->company_id)->where('code', 'VEND-002')->value('id'),
                    'batching_plant' => 'Plant Bekasi Timur', 'truck_number' => 'B 9876 TRK'.($truckIdx + 1),
                    'grade' => "fc'25", 'batch_time' => $arrival->copy()->subMinutes(35), 'arrived_at' => $arrival,
                    'pour_started_at' => $arrival->copy()->addMinutes(8), 'pour_finished_at' => $arrival->copy()->addMinutes(32),
                    'ordered_volume_m3' => $volumePerTruck, 'delivered_volume_m3' => $volumePerTruck,
                    'accepted_volume_m3' => $volumePerTruck, 'rejected_volume_m3' => '0',
                    'slump_cm' => '14', 'sample_number' => 'SMP-'.$seq.'-0'.($truckIdx + 1),
                    'status' => 'approved', 'recorded_by' => $supervisor->id, 'approved_by' => $qms->id, 'approved_at' => $arrival->copy()->addHour(),
                    'idempotency_key' => 'demo-do-'.substr($pile->pile_number, -3).'-0'.($truckIdx + 1),
                ]
            );
            // Pour intervals per truck.
            PileConcretePourInterval::firstOrCreate(
                ['bored_pile_id' => $pile->id, 'sequence' => $truckIdx + 1],
                [
                    'company_id' => $pile->project->company_id, 'recorded_at' => $arrival->copy()->addMinutes(30),
                    'depth_or_level_m' => number_format(((float) $pile->actual_depth_m / 3) * ($truckIdx + 1), 3, '.', ''),
                    'incremental_volume_m3' => $volumePerTruck,
                    'cumulative_volume_m3' => bcmul($volumePerTruck, (string) ($truckIdx + 1), 4),
                    'recorded_by' => $supervisor->id,
                ]
            );
        }

        // Update volume aktual pile via service nyata (overbreak konsisten).
        if (in_array($targetStatus, ['casting', 'testing', 'completed', 'rework'], true)) {
            app(BoredPileService::class)->recordConcrete($pile, (string) $pile->actual_depth_m, (string) $this->expectedConcrete($pile), $pm);
        }

        // Cage delivered + QC passed untuk pile fase cage ke atas.
        if (in_array($targetStatus, ['cage_installation', 'casting', 'testing', 'completed'], true)) {
            ReinforcementCage::firstOrCreate(
                ['company_id' => $pile->project->company_id, 'number' => 'CG-DEMO-'.substr($pile->pile_number, -3)],
                [
                    'bored_pile_id' => $pile->id, 'diameter_mm' => $pile->diameter_mm,
                    'main_bar_spec' => '12D16', 'spiral_spec' => 'D10-150', 'stiffener_spec' => 'D13',
                    'total_length_m' => bcadd((string) $pile->planned_depth_m, '1.200', 3),
                    'theoretical_weight_kg' => '1850.00', 'actual_weight_kg' => '1878.50',
                    'qc_status' => 'passed', 'qc_by' => $qms->id, 'qc_at' => $startAt->copy()->addDays(1),
                    'delivered_at' => $startAt->copy()->addDays(2), 'created_by' => $pm->id,
                ]
            );
        }

        // Pile testing untuk fase testing/completed/rejected.
        if (in_array($targetStatus, ['testing', 'completed', 'rejected'], true)) {
            PileTest::firstOrCreate(
                ['company_id' => $pile->project->company_id, 'number' => 'PIT-DEMO-'.substr($pile->pile_number, -3)],
                [
                    'project_id' => $pile->project->id, 'bored_pile_id' => $pile->id,
                    'test_type' => $seq % 2 === 0 ? 'PIT' : 'PDA', 'provider_name' => 'PT Uji Tanah Laboratorium',
                    'scheduled_date' => now()->subDays(max(1, 20 - ($seq % 15)))->toDateString(),
                    'result_status' => $targetStatus === 'rejected' ? 'failed' : 'passed',
                    'report_number' => 'RPT-'.substr($pile->pile_number, -3).'/UTL/'.now()->format('y'),
                    'tested_at' => now()->subDays(max(1, 19 - ($seq % 15)))->toDateString(),
                    'interpretation' => $targetStatus === 'rejected' ? 'Integritas tidak homogen pada kedalaman 12 m.' : 'Integritas baik — kapasitas > desain.',
                    'consultant_approved_at' => $targetStatus === 'completed' ? now()->subDays(10) : null,
                    'cost_amount' => $seq % 2 === 0 ? '8500000' : '12500000',
                    'recorded_by' => $qms->id,
                ]
            );
        }
    }

    private function expectedConcrete(BoredPile $pile): string
    {
        $radius = bcdiv((string) $pile->diameter_mm, '2000', 8);
        $area = bcmul(bcmul('3.14159265', bcmul($radius, $radius, 8), 8), '1', 8);

        return bcmul($area, (string) ($pile->actual_depth_m ?? $pile->planned_depth_m), 4);
    }

    /** P31: 9 skenario terkontrol supaya Risk Radar menampilkan healthy/watch/critical. */
    private function applyRiskScenarios(): void
    {
        $companyId = DemoDataSeeder::company()->id;
        $pm = DemoDataSeeder::user('pm@grahapondasi.test');
        $qms = DemoDataSeeder::user('qms@grahapondasi.test');
        $findPile = fn (string $suffix) => BoredPile::where('pile_number', 'like', '%'.$suffix)->whereIn('project_id', Project::where('company_id', $companyId)->pluck('id'))->first();

        // B: high overbreak → concrete jauh di atas teoretis.
        if ($pile = $findPile('BP-B04')) {
            app(BoredPileService::class)->recordConcrete($pile, (string) $pile->actual_depth_m, bcmul((string) $this->expectedConcrete($pile), '1.28', 4), $pm);
        }

        // C: slump fail → delivery slump 5 cm di luar 10-20.
        if ($pile = $findPile('BP-A07')) {
            ConcreteDelivery::where('bored_pile_id', $pile->id)->first()?->update(['slump_cm' => '5']);
        }

        // D: truck gap warning → DO kedua datang 3 jam setelah DO pertama selesai.
        if ($pile = $findPile('BP-A09')) {
            $deliveries = ConcreteDelivery::where('bored_pile_id', $pile->id)->orderBy('arrived_at')->get();
            if ($deliveries->count() >= 2) {
                $second = $deliveries[1];
                $newArrival = $deliveries[0]->pour_finished_at?->copy()->addHours(3);
                $second->update(['arrived_at' => $newArrival, 'pour_started_at' => $newArrival?->copy()->addMinutes(8), 'pour_finished_at' => $newArrival?->copy()->addMinutes(34)]);
            }
        }

        // E: cage QC issue → cage failed tanpa pengganti.
        if ($pile = $findPile('BP-B06')) {
            ReinforcementCage::firstOrCreate(
                ['company_id' => $companyId, 'number' => 'CG-DEMO-QCFAIL'],
                [
                    'bored_pile_id' => $pile->id, 'diameter_mm' => $pile->diameter_mm,
                    'main_bar_spec' => '12D16', 'spiral_spec' => 'D10-150',
                    'total_length_m' => bcadd((string) $pile->planned_depth_m, '1.000', 3),
                    'theoretical_weight_kg' => '1750.00', 'qc_status' => 'failed',
                    'qc_notes' => 'Demo seed: pitch spiral meleset 210mm vs spesifikasi 150mm.', 'qc_by' => $qms->id, 'qc_at' => now()->subDays(6),
                    'created_by' => $pm->id,
                ]
            );
        }

        // F: open NCR tertaut ke pile via PileTest.ncr_number.
        if ($pile = $findPile('BP-A12')) {
            $ncr = Nonconformity::firstOrCreate(
                ['company_id' => $companyId, 'number' => 'NCR-2026-002'],
                [
                    'source_type' => 'testing', 'severity' => 'critical', 'project_id' => $pile->project->id,
                    'description' => 'DEMO / SAMPLE — hasil PIT BP-A12 menunjukkan anomali impedansi pada kedalaman 14 m; investigasi lanjutan diperlukan.',
                    'reported_by' => $qms->id, 'due_at' => now()->addDays(7)->toDateString(), 'status' => 'open',
                ]
            );
            PileTest::where('bored_pile_id', $pile->id)->update(['ncr_number' => $ncr->number]);
        }

        // G: test fail sudah tercakup oleh pile rejected (BP-B09).

        // I: abnormal drilling duration → satu pile dengan durasi jauh di atas median.
        if ($pile = $findPile('BP-B03')) {
            $drilling = BoredPileDrilling::where('bored_pile_id', $pile->id)->first();
            $drilling?->update(['drilling_started_at' => now()->subDays(9)->setTime(7, 0), 'drilling_finished_at' => now()->subDays(8)->setTime(23, 30)]);
        }

        // H: missing evidence → evidence rules aktif + pile casting tanpa foto cukup (tanpa StoredFile).
        // (Secara default evidence_rules_enabled=0 sehingga tidak memaksa; scenario H diaktifkan lewat settings demo QMS.)
    }

    /** Evidence binary lokal bertanda DEMO/SAMPLE (skip bila disk tidak aman). */
    public static function storeDemoEvidence(BoredPile $pile, string $subCategory, int $companyId): ?StoredFile
    {
        $diskName = env('DEMO_SEED_STORAGE') === 'true'
            ? (string) config('objectstorage.evidence_disk', config('filesystems.evidence', 'local'))
            : 'local'; // default: JANGAN kirim file demo ke production S3.

        $objectKey = "demo-evidence/{$pile->public_uuid}/{$subCategory}.png";
        if (StoredFile::where('disk', $diskName)->where('object_key', $objectKey)->exists()) {
            return StoredFile::where('disk', $diskName)->where('object_key', $objectKey)->first();
        }

        // PNG placeholder deterministik 8x8 (base64 statis — tidak butuh GD/internet).
        $pngBytes = base_decode_demo_png();
        Storage::disk($diskName)->put($objectKey, $pngBytes);

        return StoredFile::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $companyId, 'project_id' => $pile->project_id, 'bored_pile_id' => $pile->id,
            'category' => 'photo', 'sub_category' => $subCategory, 'disk' => $diskName, 'object_key' => $objectKey,
            'original_name' => "DEMO-SAMPLE-{$subCategory}.png", 'extension' => 'png', 'mime_type' => 'image/png',
            'size_bytes' => strlen($pngBytes), 'sha256' => hash('sha256', $pngBytes),
            'status' => 'ready', 'caption' => 'DEMO / SAMPLE — placeholder buatan sistem, bukan foto lapangan.',
            'uploaded_by' => DemoDataSeeder::user('supervisor@grahapondasi.test')->id,
            'captured_at' => now()->subHours(5),
        ]);
    }
}

/** PNG 1x1 transparan minimal (konstanta biner aman-deterministik). */
function base_decode_demo_png(): string
{
    return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', true);
}
