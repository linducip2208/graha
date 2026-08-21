<?php

namespace Tests\Feature\Manufacturing;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\BillOfMaterial;
use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\Item;
use App\Models\NumberSequence;
use App\Models\ProductionOrder;
use App\Models\RoutingOperation;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\WorkCenter;
use App\Services\InventoryService;
use App\Services\ManufacturingService;
use App\Services\ManufacturingWipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ManufacturingTraceabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_issue_and_completion_are_linked_to_stock_ledger(): void
    {
        $c = Company::create(['code' => 'GP', 'name' => 'GP']);
        $u = User::factory()->create();
        FiscalPeriod::create(['company_id' => $c->id, 'name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        NumberSequence::create(['company_id' => $c->id, 'document_type' => 'journal', 'prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);
        $wip = Account::create(['company_id' => $c->id, 'code' => 'WIP', 'name' => 'WIP', 'type' => 'asset', 'normal_balance' => 'debit']);
        $rawAccount = Account::create(['company_id' => $c->id, 'code' => 'RAW', 'name' => 'Raw inventory', 'type' => 'asset', 'normal_balance' => 'debit']);
        $finished = Account::create(['company_id' => $c->id, 'code' => 'FG', 'name' => 'Finished goods', 'type' => 'asset', 'normal_balance' => 'debit']);
        $scrap = Account::create(['company_id' => $c->id, 'code' => 'SCRAP', 'name' => 'Biaya scrap', 'type' => 'expense', 'normal_balance' => 'debit']);
        $labor = Account::create(['company_id' => $c->id, 'code' => 'LAB', 'name' => 'Labor absorption', 'type' => 'expense', 'normal_balance' => 'credit']);
        $overhead = Account::create(['company_id' => $c->id, 'code' => 'OH', 'name' => 'Overhead absorption', 'type' => 'expense', 'normal_balance' => 'credit']);
        foreach ([['material_issue_manufacturing', 'wip_debit', $wip->id], ['material_issue_manufacturing', 'raw_credit', $rawAccount->id], ['production_completion', 'finished_goods_debit', $finished->id], ['production_completion', 'wip_credit', $wip->id], ['production_scrap', 'scrap_expense_debit', $scrap->id], ['production_scrap', 'wip_credit', $wip->id], ['production_conversion_cost', 'wip_debit', $wip->id], ['production_conversion_cost', 'labor_absorption_credit', $labor->id], ['production_conversion_cost', 'overhead_absorption_credit', $overhead->id]] as [$event, $side, $account]) {
            AccountingMapping::create(['company_id' => $c->id, 'event_type' => $event, 'entry_side' => $side, 'account_id' => $account]);
        }
        $unit = Unit::create(['company_id' => $c->id, 'code' => 'EA', 'name' => 'Each']);
        $raw = Item::create(['company_id' => $c->id, 'unit_id' => $unit->id, 'sku' => 'RAW', 'name' => 'Raw', 'category' => 'material']);
        $output = Item::create(['company_id' => $c->id, 'unit_id' => $unit->id, 'sku' => 'CAGE', 'name' => 'Cage', 'category' => 'finished_good']);
        $w = Warehouse::create(['company_id' => $c->id, 'code' => 'W', 'name' => 'W']);
        $bin = WarehouseBin::create(['warehouse_id' => $w->id, 'code' => 'A', 'name' => 'A']);
        $bom = BillOfMaterial::create(['company_id' => $c->id, 'output_item_id' => $output->id, 'code' => 'BOM-CAGE', 'version' => 1, 'status' => 'active']);
        $bom->items()->create(['item_id' => $raw->id, 'quantity' => '2']);
        $workCenter = WorkCenter::create(['company_id' => $c->id, 'code' => 'WELD', 'name' => 'Welding', 'labor_rate_per_hour' => '30', 'overhead_rate_per_hour' => '10']);
        $operation = RoutingOperation::create(['company_id' => $c->id, 'bill_of_material_id' => $bom->id, 'work_center_id' => $workCenter->id, 'sequence' => 10, 'name' => 'Welding cage', 'standard_minutes_per_unit' => '45']);
        $order = ProductionOrder::create(['company_id' => $c->id, 'bill_of_material_id' => $bom->id, 'warehouse_id' => $w->id, 'output_bin_id' => $bin->id, 'number' => 'MO-1', 'planned_quantity' => '2', 'created_by' => $u->id]);
        $dimension = ['warehouse_id' => $w->id, 'warehouse_bin_id' => $bin->id];
        app(InventoryService::class)->post([...$dimension, 'company_id' => $c->id, 'item_id' => $raw->id], 'receipt', '4', 'raw-receipt', $u, ['type' => 'gr', 'id' => '1'], '10.00');
        $manufacturing = app(ManufacturingService::class);
        $manufacturing->issueMaterial($order, $raw, $dimension, '4', $u, 'issue');
        try {
            $manufacturing->issueMaterial($order->refresh(), $raw, $dimension, '0.1', $u, 'issue-over-bom');
            $this->fail('Material issue melebihi BOM seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
        $operationLog = $manufacturing->recordOperation($order->refresh(), $operation, ['quantity_processed' => '2', 'actual_hours' => '2', 'notes' => 'Realisasi welding', 'idempotency_key' => 'operation-1'], $u);
        $manufacturing->inspect($order->refresh(), ['number' => 'QC-1', 'inspected_quantity' => '1', 'result' => 'accepted', 'criteria' => 'Dimensi dan welding sesuai drawing', 'evidence_reference' => 'CHECKSHEET-1'], $u);
        $done = $manufacturing->complete($order->refresh(), '1', $u, 'done');
        $rejected = $manufacturing->inspect($order->refresh(), ['number' => 'QC-2', 'inspected_quantity' => '1', 'result' => 'rejected', 'criteria' => 'Dimensi dan welding sesuai drawing', 'findings' => 'Dimensi di luar toleransi'], $u);
        $disposition = $manufacturing->dispose($rejected, ['number' => 'DSP-1', 'disposition' => 'scrap', 'quantity' => '1', 'reason' => 'Tidak dapat diperbaiki', 'instruction' => 'Pisahkan ke area scrap', 'idempotency_key' => 'scrap-1'], $u);
        $this->assertSame('in_progress', $done->status);
        $this->assertDatabaseHas('production_material_issues', ['production_order_id' => $order->id, 'item_id' => $raw->id]);
        $this->assertDatabaseHas('stock_balances', ['item_id' => $raw->id, 'quantity' => 0]);
        $this->assertDatabaseHas('stock_balances', ['item_id' => $output->id, 'quantity' => 1]);
        $this->assertDatabaseCount('journals', 4);
        $this->assertDatabaseHas('journal_entries', ['account_id' => $wip->id, 'debit' => '40.00']);
        $this->assertDatabaseHas('journal_entries', ['account_id' => $wip->id, 'debit' => '80.00']);
        $this->assertDatabaseHas('journal_entries', ['account_id' => $labor->id, 'credit' => '60.00']);
        $this->assertDatabaseHas('journal_entries', ['account_id' => $overhead->id, 'credit' => '20.00']);
        $this->assertDatabaseHas('journal_entries', ['account_id' => $finished->id, 'debit' => '60.00']);
        $this->assertDatabaseHas('journal_entries', ['account_id' => $scrap->id, 'debit' => '60.00']);
        $this->assertDatabaseHas('production_inspections', ['production_order_id' => $order->id, 'result' => 'accepted']);
        $this->assertDatabaseHas('production_dispositions', ['id' => $disposition->id, 'disposition' => 'scrap', 'quantity' => '1.0000']);
        $this->assertDatabaseHas('production_operation_logs', ['id' => $operationLog->id, 'labor_cost' => '60.00', 'overhead_cost' => '20.00']);
        $this->assertDatabaseHas('production_orders', ['id' => $order->id, 'completed_cost' => '60.00']);
        $this->assertSame('60.00', (string) $disposition->cost_amount);
        $this->assertSame('completed_with_scrap', $order->refresh()->status);
        $reconciliation = app(ManufacturingWipService::class)->reconcile($c->id)->first();
        $this->assertSame('0.00', $reconciliation['residual_wip']);
        $this->assertFalse($reconciliation['anomaly']);
        $order->update(['completed_cost' => '50.00']);
        $this->assertTrue(app(ManufacturingWipService::class)->reconcile($c->id)->first()['anomaly']);
    }
}
