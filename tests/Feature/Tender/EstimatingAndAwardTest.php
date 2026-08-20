<?php

namespace Tests\Feature\Tender;

use App\Models\Company;
use App\Models\Customer;
use App\Models\ProjectAward;
use App\Models\Tender;
use App\Models\User;
use App\Services\EstimatingService;
use App\Services\ProjectAwardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EstimatingAndAwardTest extends TestCase
{
    use RefreshDatabase;

    public function test_boq_rab_rap_totals_are_calculated_as_decimal(): void
    {
        [$company, $customer, $user] = $this->base();
        $tender = Tender::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'number' => 'T-1', 'year' => 2026, 'project_name' => 'Tower', 'created_by' => $user->id]);
        $estimate = app(EstimatingService::class)->createRevision($tender, $user, [[
            'code' => 'BP-001', 'description' => 'Bored pile D800', 'uom' => 'm', 'quantity' => '10.5000',
            'boq_unit_price' => '1200000.00', 'rab_unit_cost' => '900000.00', 'rap_unit_cost' => '850000.00',
        ]], 'Baseline');
        $this->assertSame('12600000.00', $estimate->boq_total);
        $this->assertSame('9450000.00', $estimate->rab_total);
        $this->assertSame('8925000.00', $estimate->rap_total);
        $this->assertSame('9450000.00', $tender->refresh()->estimated_cost);
    }

    public function test_activation_is_blocked_until_all_gates_and_handover_complete(): void
    {
        [$company, $customer, $user] = $this->base();
        $award = ProjectAward::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'source' => 'direct_award', 'award_type' => 'spk', 'award_number' => 'SPK-1', 'award_date' => '2026-08-21', 'contract_value' => '100000000.00']);
        $service = app(ProjectAwardService::class);
        $handover = $service->prepareHandover($award, $user);
        try {
            $service->activate($award, $user);
            $this->fail('Gate harus menolak aktivasi.');
        } catch (ValidationException) {
            $this->assertSame('received', $award->refresh()->status);
        }
        $award->update(['legal_approved' => true, 'finance_tax_approved' => true, 'signed' => true, 'project_manager_id' => $user->id]);
        $handover->items()->update(['is_complete' => true]);
        $this->assertSame('effective', $service->activate($award, $user)->status);
        $this->assertNotNull($handover->refresh()->completed_at);
    }

    private function base(): array
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C-1', 'name' => 'Client']);

        return [$company, $customer, User::factory()->create()];
    }
}
