<?php

namespace Tests\Feature\Procurement;

use App\Models\Company;
use App\Models\Item;
use App\Models\Rfq;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorQuotation;
use App\Services\RfqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RfqTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_flow_invite_quote_compare_select(): void
    {
        [$company, $user, $rfq, $vendorA, $vendorB] = $this->fixture();
        $service = app(RfqService::class);

        $service->invite($rfq, [$vendorA->id, $vendorB->id], $user);
        $this->assertSame(2, $rfq->vendors()->count());

        $service->submitQuotation($rfq, $vendorA->id, ['number' => 'Q-A', 'delivery_lead_days' => 7], [
            ['item_id' => $this->itemId($rfq, 'ITM-A'), 'quantity' => '10', 'unit_price' => '100'],
            ['item_id' => $this->itemId($rfq, 'ITM-B'), 'quantity' => '5', 'unit_price' => '200'],
        ], $user);
        $service->submitQuotation($rfq, $vendorB->id, ['number' => 'Q-B'], [
            ['item_id' => $this->itemId($rfq, 'ITM-A'), 'quantity' => '10', 'unit_price' => '90'],
            ['item_id' => $this->itemId($rfq, 'ITM-B'), 'quantity' => '5', 'unit_price' => '200'],
        ], $user);

        $comparison = $service->compare($rfq->refresh());
        $this->assertSame('B', $comparison[0]['vendor']);
        $this->assertSame('1900.00', $comparison[0]['total']);
        $this->assertSame('2000.00', $comparison[1]['total']);

        $cheapest = VendorQuotation::where('rfq_id', $rfq->id)->where('number', 'Q-B')->first();
        $service->select($cheapest, $user);

        $this->assertSame('selected', $cheapest->refresh()->status);
        $this->assertSame('closed', $rfq->refresh()->status);
        $loser = VendorQuotation::where('number', 'Q-A')->first();
        $this->assertSame('rejected', $loser->status);

        $this->expectException(ValidationException::class);
        $service->submitQuotation($rfq->refresh(), $vendorA->id, ['number' => 'Q-A2'], [], $user);
    }

    public function test_quotation_requires_invitation_and_exact_items(): void
    {
        [$company, $user, $rfq, $vendorA, $vendorB] = $this->fixture();
        $service = app(RfqService::class);
        $service->invite($rfq, [$vendorA->id], $user);

        try {
            $service->submitQuotation($rfq, $vendorB->id, ['number' => 'Q-X'], [
                ['item_id' => $this->itemId($rfq, 'ITM-A'), 'quantity' => '10', 'unit_price' => '1'],
                ['item_id' => $this->itemId($rfq, 'ITM-B'), 'quantity' => '5', 'unit_price' => '1'],
            ], $user);
            $this->fail('Harus ditolak: vendor tidak diundang.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('vendor', $e->errors());
        }

        $this->expectException(ValidationException::class);
        $service->submitQuotation($rfq, $vendorA->id, ['number' => 'Q-PARTIAL'], [
            ['item_id' => $this->itemId($rfq, 'ITM-A'), 'quantity' => '10', 'unit_price' => '1'],
        ], $user);
    }

    public function test_cross_company_vendor_rejected_from_invitation(): void
    {
        [$companyA, $userA, $rfqA] = $this->fixture('RA');
        $companyB = Company::create(['code' => 'RB2', 'name' => 'RB2']);
        $foreignVendor = Vendor::create(['company_id' => $companyB->id, 'code' => 'V-FOREIGN', 'name' => 'Foreign']);
        $service = app(RfqService::class);

        $this->expectException(ValidationException::class);
        $service->invite($rfqA, [$foreignVendor->id], $userA);
    }

    public function test_duplicate_rfq_number_rejected_within_company(): void
    {
        [$companyA, $userA, $rfqA] = $this->fixture('RC');

        $this->expectException(ValidationException::class);
        app(RfqService::class)->create($companyA->id, ['number' => $rfqA->number, 'title' => 'Duplikat'], [
            ['sku' => 'ITM-A', 'quantity' => '1'],
        ], $userA);
    }

    private function itemId(Rfq $rfq, string $sku): int
    {
        return $rfq->items()->whereHas('item', fn ($q) => $q->where('sku', $sku))->value('item_id');
    }

    private function fixture(string $code = 'RFX'): array
    {
        $company = Company::create(['code' => $code, 'name' => $code]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $unit = Unit::create(['company_id' => $company->id, 'code' => 'PCS', 'name' => 'Piece']);
        foreach ([['ITM-A', 100], ['ITM-B', 200]] as [$sku, $price]) {
            Item::create(['company_id' => $company->id, 'sku' => $sku, 'name' => $sku, 'category' => 'Material', 'unit_id' => $unit->id]);
        }
        $rfq = app(RfqService::class)->create($company->id, ['number' => "RFQ-$code", 'title' => 'Pengadaan uji'], [
            ['sku' => 'ITM-A', 'quantity' => '10'], ['sku' => 'ITM-B', 'quantity' => '5'],
        ], $user);
        $vendorA = Vendor::create(['company_id' => $company->id, 'code' => 'VA', 'name' => 'A']);
        $vendorB = Vendor::create(['company_id' => $company->id, 'code' => 'VB', 'name' => 'B']);

        return [$company, $user, $rfq, $vendorA, $vendorB];
    }
}
