<?php

namespace Tests\Feature\Tender;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Tender;
use App\Models\TenderParticipant;
use App\Models\User;
use App\Services\TenderIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TenderIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_requires_active_membership_of_tender_company(): void
    {
        [$companyA, $userA, $tenderA] = $this->fixture('GA');
        $companyB = Company::create(['code' => 'GB', 'name' => 'GB']);
        $userB = User::factory()->create();
        $userB->companies()->attach($companyB->id, ['is_default' => true, 'is_active' => true]);

        $this->expectException(ValidationException::class);
        app(TenderIntelligenceService::class)->addParticipant($tenderA, ['name' => 'CV Pesaing'], $userB);
    }

    public function test_win_rate_and_price_difference_formulas(): void
    {
        [$company, $user] = $this->fixture('GC');
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C', 'name' => 'Client']);
        $mk = fn (string $number, string $status, ?string $hps, ?string $bid) => Tender::create([
            'company_id' => $company->id, 'customer_id' => $customer->id, 'number' => $number, 'year' => 2026,
            'project_name' => $number, 'location' => '-', 'work_type' => 'bored_pile', 'owner_estimate' => $hps,
            'bid_value' => $bid, 'estimated_cost' => null, 'status' => $status, 'created_by' => $user->id,
        ]);
        $mk('T-001', 'won', '1000', '950');
        $mk('T-002', 'won', '2000', '2100');
        $mk('T-003', 'lost', '1500', '1600');
        $mk('T-004', 'draft', '500', null);

        $stats = app(TenderIntelligenceService::class)->stats($company->id);

        $this->assertSame(2, $stats['won']);
        $this->assertSame(1, $stats['lost']);
        $this->assertSame(66.7, $stats['win_rate']);
        $this->assertSame(33.3, $stats['loss_rate']);
        $this->assertSame('3050.00', $stats['won_value']);
        $avgVsHps = ((950 - 1000) / 1000 * 100 + (2100 - 2000) / 2000 * 100) / 2;
        $this->assertEqualsWithDelta($avgVsHps, $stats['avg_vs_hps_pct'], 0.01);
    }

    public function test_winner_flag_is_exclusive_per_tender(): void
    {
        [$company, $user, $tender] = $this->fixture('GD');
        $service = app(TenderIntelligenceService::class);

        $service->addParticipant($tender, ['name' => 'PT Satu', 'is_winner' => true], $user);
        $service->addParticipant($tender, ['name' => 'PT Dua', 'is_winner' => true], $user);

        $winners = TenderParticipant::where('tender_id', $tender->id)->where('is_winner', true)->get();
        $this->assertSame(1, $winners->count());
        $this->assertSame('PT Dua', $winners[0]->name);
    }

    private function fixture(string $code): array
    {
        $company = Company::create(['code' => $code, 'name' => $code]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C-'.$code, 'name' => 'Client']);
        $tender = Tender::create([
            'company_id' => $company->id, 'customer_id' => $customer->id, 'number' => 'TNDR-'.$code,
            'year' => 2026, 'project_name' => 'Proyek '.$code, 'location' => '-', 'work_type' => 'bored_pile',
            'owner_estimate' => null, 'bid_value' => null, 'estimated_cost' => null, 'status' => 'bidding',
            'created_by' => $user->id,
        ]);

        return [$company, $user, $tender];
    }
}
