<?php

namespace Tests\Feature\Inventory;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\Item;
use App\Models\Journal;
use App\Models\MaterialRequest;
use App\Models\NumberSequence;
use App\Models\Project;
use App\Models\ProjectCostLedger;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\MaterialRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MaterialRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_posts_movement_balanced_journal_and_project_cost_ledger_once(): void
    {
        [$company, $requester, $approver, $mr] = $this->fixture();
        $service = app(MaterialRequestService::class);
        $service->approve($mr, $approver);

        $issued = $service->issue($mr->refresh(), $requester);

        $this->assertSame('50.0000', (string) $issued->lines[0]->issued_quantity);
        $balance = StockBalance::where('item_id', $issued->lines[0]->item_id)->first();
        $this->assertSame('0.0000', (string) $balance->quantity);

        $journal = Journal::where('source_type', 'material_issue')->where('source_id', (string) $mr->id)->with('entries')->first();
        $debit = $journal->entries->reduce(fn ($s, $e) => bcadd($s, $e->debit, 2), '0');
        $credit = $journal->entries->reduce(fn ($s, $e) => bcadd($s, $e->credit, 2), '0');
        $this->assertSame($debit, $credit);
        $this->assertSame('50000.00', number_format((float) $debit, 2, '.', ''));
        $costRow = ProjectCostLedger::where('project_id', $mr->project_id)->first();
        $this->assertNotNull($costRow);
        $this->assertSame('50000.00', number_format((float) $costRow->amount, 2, '.', ''));

        try {
            $service->issue($issued, $requester);
            $this->fail('Issue kedua harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame('Semua baris sudah diterbitkan.', $e->errors()['issue'][0]);
        }
    }

    public function test_return_reverses_stock_journal_and_project_cost(): void
    {
        [$company, $requester, $approver, $mr] = $this->fixture();
        $service = app(MaterialRequestService::class);
        $service->approve($mr, $approver);
        $service->issue($mr->refresh(), $requester);

        $service->returnLine($mr->refresh(), $mr->lines[0]->id, '10', $approver);

        $balance = StockBalance::where('item_id', $mr->lines[0]->item_id)->first();
        $this->assertSame('10.0000', (string) $balance->quantity);
        $this->assertSame('40.0000', (string) $mr->refresh()->lines[0]->issued_quantity);

        $netCost = ProjectCostLedger::where('project_id', $mr->project_id)->get()->reduce(fn ($s, $r) => bcadd($s, (string) $r->amount, 2), '0');
        $this->assertSame('40000.00', number_format((float) $netCost, 2, '.', ''));

        $returnJournal = Journal::where('source_type', 'material_return')->with('entries')->latest('id')->first();
        $debit = $returnJournal->entries->reduce(fn ($s, $e) => bcadd($s, $e->debit, 2), '0');
        $credit = $returnJournal->entries->reduce(fn ($s, $e) => bcadd($s, $e->credit, 2), '0');
        $this->assertSame($debit, $credit);
    }

    public function test_approver_must_differ_from_requester(): void
    {
        [, $requester, , $mr] = $this->fixture();

        $this->expectException(ValidationException::class);
        app(MaterialRequestService::class)->approve($mr, $requester);
    }

    public function test_issue_requires_approved_status(): void
    {
        [$company, $requester, , $mr] = $this->fixture();

        $this->expectException(ValidationException::class);
        app(MaterialRequestService::class)->issue($mr, $requester);
    }

    private function fixture(): array
    {
        $company = Company::create(['code' => 'MRX', 'name' => 'MRX']);
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        foreach ([$requester, $approver] as $u) {
            $u->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        }
        FiscalPeriod::create(['company_id' => $company->id, 'name' => 'FY2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        NumberSequence::create(['company_id' => $company->id, 'document_type' => 'journal', 'prefix' => 'JV', 'padding' => 5, 'last_reset_year' => 2026]);
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C', 'name' => 'Client']);
        $project = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P', 'name' => 'Proyek', 'status' => 'in_progress']);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'code' => 'WH', 'name' => 'Gudang']);
        $unit = Unit::create(['company_id' => $company->id, 'code' => 'TON', 'name' => 'Ton']);
        $item = Item::create(['company_id' => $company->id, 'sku' => 'BESI', 'name' => 'Besi', 'category' => 'M', 'unit_id' => $unit->id]);
        app(InventoryService::class)->post([
            'company_id' => $company->id, 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'warehouse_bin_id' => null,
        ], 'receipt', '50', 'seed-besi', $approver, ['type' => 'opening', 'id' => 'besi'], '1000');

        foreach ([['debit', '5-MAT'], ['credit', '1-WH']] as [$side, $code]) {
            $account = Account::firstOrCreate(['company_id' => $company->id, 'code' => $code], ['name' => $code, 'type' => $side === 'debit' ? 'expense' : 'asset', 'normal_balance' => $side]);
            AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'material_issue', 'entry_side' => $side, 'account_id' => $account->id]);
        }
        $mr = MaterialRequest::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'warehouse_id' => $warehouse->id,
            'number' => 'MR-1', 'requested_by' => $requester->id,
        ]);
        $mr->lines()->create(['item_id' => $item->id, 'quantity' => '50']);

        return [$company, $requester, $approver, $mr];
    }
}
