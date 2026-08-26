<?php

namespace Database\Seeders;

use App\Models\ContractCorrespondence;
use App\Models\ContractInsurance;
use App\Models\ContractMilestone;
use App\Models\Customer;
use App\Models\ProjectAward;
use App\Models\Tender;
use App\Models\TenderOutcome;
use App\Models\TenderParticipant;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * Demo komersial (ADR-079): 4 customer, 8 vendor, 5 tender (won/lost/
 * submitted/evaluation/no-bid) dengan outcome + peserta kompetitor, dan
 * 3 kontrak (2 aktif + 1 selesai) lengkap milestone/asuransi/korespondensi.
 */
class DemoCommercialSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = DemoDataSeeder::company()->id;
        $director = DemoDataSeeder::user('direktur@grahapondasi.test');

        $customerCodes = [
            'CUST-001' => 'PT Wijaya Karya Bangunan',
            'CUST-002' => 'PT Logistik Nusantara Infra',
            'CUST-003' => 'PT Properti Kota Baru',
            'CUST-004' => 'Dinas Bina Marga Provinsi',
        ];
        foreach ($customerCodes as $code => $name) {
            Customer::firstOrCreate(['company_id' => $companyId, 'code' => $code], ['name' => $name]);
        }

        $vendors = [
            ['VEND-001', 'CV Besi Baja Nusantara', '01.234.567.8-999.000'],
            ['VEND-002', 'PT Beton Ready Mix Sejahtera', '02.345.678.9-051.000'],
            ['VEND-003', 'PT Bentonite Prima Indonesia', '03.456.789.0-051.000'],
            ['VEND-004', 'CV Alat Berat Mandiri', '04.567.890.1-052.000'],
            ['VEND-005', 'PT Uji Tanah Laboratorium', '05.678.901.2-053.000'],
            ['VEND-006', 'PT Solar Niaga Energi', '06.789.012.3-054.000'],
            ['VEND-007', 'CV Kelistrikan Site', '07.890.123.4-055.000'],
            ['VEND-008', 'PT Subkon Pondasi Jaya', '08.901.234.5-056.000'],
        ];
        foreach ($vendors as [$code, $name, $npwp]) {
            Vendor::firstOrCreate(['company_id' => $companyId, 'code' => $code], ['name' => $name, 'tax_number' => $npwp]);
        }

        // 5 tender dengan status beragam.
        $tenders = [
            ['TND-DEMO-001', 1, 'Fondasi Bored Pile Tower Transmisi Cikarang', 'won', now()->subDays(120)->toDateString(), '8500000000', '7800000000', 'PT Wijaya Karya Bangunan'],
            ['TND-DEMO-002', 2, 'Pondasi Gudang Logistik Karawang', 'lost', now()->subDays(80)->toDateString(), '4200000000', '3950000000', 'PT Kontraktor Saingan Utama'],
            ['TND-DEMO-003', 3, 'Bored Pile Jembatan Akses Bekasi', 'submitted', now()->subDays(20)->toDateString(), '3100000000', null, null],
            ['TND-DEMO-004', 4, 'Pondasi Depot BBM Subang', 'evaluation', now()->subDays(8)->toDateString(), '5600000000', null, null],
            ['TND-DEMO-005', 1, 'Retaining Wall Pasar Induk', 'no_bid', now()->subDays(60)->toDateString(), '900000000', null, null],
        ];
        foreach ($tenders as [$number, $customerId, $projectName, $status, $date, $ownerEstimate, $bidValue, $winner]) {
            $tender = Tender::firstOrCreate(
                ['company_id' => $companyId, 'number' => $number],
                [
                    'year' => now()->format('Y'),
                    'customer_id' => $customerId, 'project_name' => $projectName, 'location' => 'Jawa Barat',
                    'work_type' => 'Bored Pile Foundation', 'owner_estimate' => $ownerEstimate,
                    'bid_value' => $bidValue ?? '0', 'estimated_cost' => (string) (int) ((float) $ownerEstimate * 0.82),
                    'status' => $status, 'created_by' => $director->id,
                ]
            );
            if (! in_array($status, ['draft', 'evaluation'], true)) {
                TenderOutcome::firstOrCreate(['tender_id' => $tender->id], [
                    'outcome' => match ($status) {
                        'won' => 'win', 'lost' => 'lost', default => 'no_bid',
                    },
                    'announced_at' => $date,
                    'winner_name' => $winner,
                    'winning_bid_value' => $bidValue,
                    'primary_reason' => $status === 'lost' ? 'Harga tidak kompetitif - margin RAP di bawah pesaing.' : null,
                    'recorded_by' => $director->id,
                ]);
            }
            // Peserta kompetitor untuk tender yang dilombakan.
            if ($status !== 'no_bid') {
                foreach ([['PT Konkuren Abadi', '950000000'], ['CV Mitra Teknik', '820000000']] as $i => [$competitor, $price]) {
                    TenderParticipant::firstOrCreate(['tender_id' => $tender->id, 'name' => $competitor], [
                        'company_id' => $companyId,
                        'bid_value' => $price, 'is_winner' => false, 'rank' => $i + 2,
                        'recorded_by' => $director->id,
                    ]);
                }
            }
        }

        // 3 kontrak: 2 aktif + 1 selesai.
        $awards = [
            ['AWD-DEMO-001', 'TND-DEMO-001', 1, 'active', '2500000000', true],
            ['AWD-DEMO-002', 'TND-DEMO-002', 2, 'active', '1800000000', false],
            ['AWD-DEMO-003', 'TND-DEMO-003', 4, 'completed', '2900000000', true],
        ];
        foreach ($awards as [$awardNumber, $tenderNumber, $customerId, $status, $value, $signed]) {
            $tender = Tender::where('company_id', $companyId)->where('number', $tenderNumber)->first();
            $award = ProjectAward::firstOrCreate(['company_id' => $companyId, 'award_number' => $awardNumber], [
                'tender_id' => $tender?->id, 'customer_id' => $customerId, 'source' => 'tender',
                'award_type' => 'unit_price', 'award_date' => now()->subDays(100)->toDateString(),
                'contract_value' => $value, 'retention_percent' => '5',
                'status' => $status, 'legal_approved' => true, 'finance_tax_approved' => true, 'signed' => $signed,
                'project_manager_id' => DemoDataSeeder::user('pm@grahapondasi.test')->id,
            ]);
            if ($award->wasRecentlyCreated || ContractMilestone::where('project_award_id', $award->id)->count() === 0) {
                foreach ([[10, 'Mobilisasi rig & platform', 5, 'achieved'], [30, 'Drilling pile zona utama', 40, $status === 'completed' ? 'achieved' : 'pending'], [60, 'Casting & pengujian', 35, 'pending'], [120, 'Serah terima dokumen', 20, 'pending']] as [$offsetDays, $mName, $weight, $mStatus]) {
                    ContractMilestone::firstOrCreate(['project_award_id' => $award->id, 'name' => $mName], [
                        'company_id' => $companyId,
                        'weight_percent' => $weight, 'amount' => bcmul($value, bcdiv((string) $weight, '100', 4), 2),
                        'planned_date' => now()->addDays($offsetDays)->toDateString(), 'status' => $mStatus,
                    ]);
                }
            }
            ContractInsurance::firstOrCreate(['project_award_id' => $award->id, 'policy_number' => "CAR/{$awardNumber}"], [
                'company_id' => $companyId,
                'provider' => 'PT Asuransi Konstruksi', 'coverage_type' => 'car', 'insured_amount' => $value,
                'premium' => '14500000', 'start_date' => now()->subDays(95)->toDateString(),
                'end_date' => now()->addDays(270)->toDateString(), 'created_by' => $director->id,
            ]);
            ContractCorrespondence::firstOrCreate(['project_award_id' => $award->id, 'ref_number' => "COR/{$awardNumber}/001"], [
                'company_id' => $companyId,
                'direction' => 'in', 'correspondence_date' => now()->subDays(50)->toDateString(),
                'subject' => 'Persetujuan shop drawing fondasi', 'body' => 'Shop drawing disetujui dengan catatan tambahan casing pada segmen pasir.',
                'created_by' => $director->id,
            ]);
        }
    }
}
