<?php

namespace Tests\Feature\Procurement;

use App\Models\Company;
use App\Models\Item;
use App\Models\PurchaseRequest;
use App\Models\Unit;
use App\Models\User;
use App\Services\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseRequestBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_pr_over_budget_is_blocked(): void
    {
        $c = Company::create(['code' => 'GP', 'name' => 'GP']);
        $u = User::factory()->create();
        $unit = Unit::create(['company_id' => $c->id, 'code' => 'EA', 'name' => 'Each']);
        $item = Item::create(['company_id' => $c->id, 'unit_id' => $unit->id, 'sku' => 'X', 'name' => 'Item', 'category' => 'material']);
        $pr = PurchaseRequest::create(['company_id' => $c->id, 'number' => 'PR-1', 'budget_available' => '1000', 'requested_by' => $u->id]);
        $pr->items()->create(['item_id' => $item->id, 'quantity' => '2', 'estimated_unit_price' => '600']);
        $this->expectException(ValidationException::class);
        app(PurchaseRequestService::class)->submit($pr, $u);
    }
}
