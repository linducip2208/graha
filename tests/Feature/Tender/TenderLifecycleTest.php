<?php

namespace Tests\Feature\Tender;

use App\Models\Company;
use App\Models\Customer;
use App\Models\NumberSequence;
use App\Models\Tender;
use App\Models\User;
use App\Services\TenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function tender(string $status = 'submitted'): array
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'CUS-1', 'name' => 'Client']);
        $user = User::factory()->create();
        NumberSequence::create(['company_id' => $company->id, 'document_type' => 'project', 'prefix' => 'PRJ', 'padding' => 4, 'last_reset_year' => 2026]);
        $tender = Tender::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'number' => 'T-001', 'year' => 2026, 'project_name' => 'Bored Pile Tower', 'bid_value' => '1250000000.00', 'estimated_cost' => '1000000000.00', 'status' => $status, 'created_by' => $user->id]);

        return [$company, $user, $tender];
    }

    public function test_won_tender_converts_once_without_reentry(): void
    {
        [$company, $user, $tender] = $this->tender();
        app(TenderService::class)->recordOutcome($tender, $user, 'won', ['announced_at' => '2026-08-21', 'contract_value' => '1200000000.00']);
        $first = app(TenderService::class)->convertWonToProject($tender, $user);
        $second = app(TenderService::class)->convertWonToProject($tender, $user);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('1200000000.00', $first->contract_value);
        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'event' => 'tender.converted_to_project']);
    }

    public function test_lost_metrics_handle_zero_and_capture_analysis(): void
    {
        [$company, $user, $tender] = $this->tender();
        $empty = app(TenderService::class)->metrics($company->id, 2025);
        $this->assertSame(0.0, $empty['win_rate']);
        app(TenderService::class)->recordOutcome($tender, $user, 'lost', ['announced_at' => '2026-08-21', 'winner_name' => 'Kompetitor', 'winning_bid_value' => '1100000000.00', 'primary_reason' => 'Harga terlalu tinggi', 'lesson_learned' => 'Optimasi metode kerja']);
        $metrics = app(TenderService::class)->metrics($company->id, 2026);
        $this->assertSame(0.0, $metrics['win_rate']);
        $this->assertSame(100.0, $metrics['loss_rate']);
        $this->assertDatabaseHas('tender_outcomes', ['tender_id' => $tender->id, 'primary_reason' => 'Harga terlalu tinggi']);
    }
}
