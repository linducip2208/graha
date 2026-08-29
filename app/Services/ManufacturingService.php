<?php

namespace App\Services;

use App\Models\AccountingMapping;
use App\Models\Item;
use App\Models\Journal;
use App\Models\ProductionDisposition;
use App\Models\ProductionInspection;
use App\Models\ProductionMaterialIssue;
use App\Models\ProductionOperationLog;
use App\Models\ProductionOrder;
use App\Models\RoutingOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManufacturingService
{
    public function __construct(private InventoryService $inventory, private AccountingService $accounting, private AuditTrail $audit) {}

    public function issueMaterial(ProductionOrder $order, Item $item, array $dimension, string $quantity, User $actor, string $key): ProductionMaterialIssue
    {
        return DB::transaction(function () use ($order, $item, $dimension, $quantity, $actor, $key) {
            $order = ProductionOrder::lockForUpdate()->findOrFail($order->id);
            throw_unless(in_array($order->status, ['planned', 'released', 'in_progress'], true), ValidationException::withMessages(['order' => 'Production order tidak aktif.']));
            $component = $order->bom->items()->where('item_id', $item->id)->first();
            throw_unless($component, ValidationException::withMessages(['item' => 'Material tidak terdaftar pada BOM production order.']));
            $baseRequirement = bcdiv(bcmul((string) $order->planned_quantity, (string) $component->quantity, 8), (string) $order->bom->output_quantity, 4);
            $allowance = bcdiv(bcmul($baseRequirement, (string) $component->scrap_percent, 8), '100', 4);
            $maximum = bcadd($baseRequirement, $allowance, 4);
            $issued = (string) ProductionMaterialIssue::where('production_order_id', $order->id)->where('item_id', $item->id)->sum('quantity');
            throw_if(bccomp(bcadd($issued, $quantity, 4), $maximum, 4) === 1, ValidationException::withMessages(['quantity' => "Material issue melebihi kebutuhan BOM termasuk allowance scrap. Maksimum {$maximum}, sudah dikeluarkan {$issued}."]));
            $movement = $this->inventory->post([...$dimension, 'company_id' => $order->company_id, 'item_id' => $item->id], 'issue', $quantity, 'production:'.$order->id.':'.$key, $actor, ['type' => 'production_order', 'id' => $order->id]);
            $issue = ProductionMaterialIssue::firstOrCreate(['stock_movement_id' => $movement->id], ['production_order_id' => $order->id, 'item_id' => $item->id, 'quantity' => $quantity, 'lot_number' => $dimension['lot_number'] ?? '']);
            $cost = bcmul($quantity, (string) $movement->unit_cost, 2);
            throw_if(bccomp($cost, '0', 2) <= 0, ValidationException::withMessages(['cost' => 'Material produksi harus mempunyai unit cost.']));
            $maps = $this->mappings($order->company_id, 'material_issue_manufacturing', ['wip_debit', 'raw_credit']);
            $this->accounting->post($order->company_id, now()->toDateString(), 'material_issue_manufacturing', (string) $issue->id, 'Material issue '.$order->number, [
                ['account_id' => $maps['wip_debit']->account_id, 'debit' => $cost, 'credit' => '0', 'project_id' => $order->project_id],
                ['account_id' => $maps['raw_credit']->account_id, 'debit' => '0', 'credit' => $cost, 'project_id' => $order->project_id],
            ], 'manufacturing-issue:'.$order->id.':'.$key, $actor);
            $order->update(['status' => 'in_progress', 'actual_material_cost' => bcadd((string) $order->actual_material_cost, $cost, 2)]);

            return $issue;
        }, 3);
    }

    public function complete(ProductionOrder $order, string $quantity, User $actor, string $key): ProductionOrder
    {
        return DB::transaction(function () use ($order, $quantity, $actor, $key) {
            $completionKey = 'manufacturing-completion:'.$order->id.':'.$key;
            if ($existing = Journal::where('company_id', $order->company_id)->where('idempotency_key', $completionKey)->first()) {
                return $order->refresh();
            }
            $order = ProductionOrder::with('bom')->lockForUpdate()->findOrFail($order->id);
            throw_if(bccomp($quantity, '0', 4) <= 0, ValidationException::withMessages(['quantity' => 'Output harus positif.']));
            $after = bcadd((string) $order->completed_quantity, $quantity, 4);
            throw_if(bccomp($after, (string) $order->planned_quantity, 4) === 1, ValidationException::withMessages(['quantity' => 'Output melebihi rencana.']));
            $released = (string) ProductionInspection::where('production_order_id', $order->id)->where('result', 'accepted')->sum('inspected_quantity');
            throw_if(bccomp($after, $released, 4) === 1, ValidationException::withMessages(['inspection' => 'Kuantitas output belum dirilis oleh Quality Control.']));
            foreach ($order->bom->routingOperations as $operation) {
                $processed = (string) ProductionOperationLog::where('production_order_id', $order->id)->where('routing_operation_id', $operation->id)->sum('quantity_processed');
                throw_if(bccomp($processed, $after, 4) === -1, ValidationException::withMessages(['routing' => "Operasi {$operation->sequence} - {$operation->name} baru memproses {$processed}; kebutuhan completion {$after}. "]));
            }
            $actualCost = bcadd(bcadd((string) $order->actual_material_cost, (string) $order->actual_labor_cost, 2), (string) $order->actual_overhead_cost, 2);
            $scrappedCost = (string) ProductionDisposition::whereHas('inspection', fn ($query) => $query->where('production_order_id', $order->id))->where('disposition', 'scrap')->sum('cost_amount');
            $unallocatedCost = bcsub(bcsub($actualCost, (string) $order->completed_cost, 2), $scrappedCost, 2);
            $completionCost = bccomp($after, (string) $order->planned_quantity, 4) === 0 ? $unallocatedCost : bcdiv(bcmul($actualCost, $quantity, 4), (string) $order->planned_quantity, 2);
            throw_if(bccomp($completionCost, '0', 2) <= 0, ValidationException::withMessages(['cost' => 'Production order belum mempunyai material cost.']));
            $this->inventory->post(['company_id' => $order->company_id, 'item_id' => $order->bom->output_item_id, 'warehouse_id' => $order->warehouse_id, 'warehouse_bin_id' => $order->output_bin_id], 'receipt', $quantity, 'production-complete:'.$order->id.':'.$key, $actor, ['type' => 'production_order', 'id' => $order->id], bcdiv($completionCost, $quantity, 4));
            $maps = $this->mappings($order->company_id, 'production_completion', ['finished_goods_debit', 'wip_credit']);
            $this->accounting->post($order->company_id, now()->toDateString(), 'production_completion', (string) $order->id, 'Production completion '.$order->number, [
                ['account_id' => $maps['finished_goods_debit']->account_id, 'debit' => $completionCost, 'credit' => '0', 'project_id' => $order->project_id],
                ['account_id' => $maps['wip_credit']->account_id, 'debit' => '0', 'credit' => $completionCost, 'project_id' => $order->project_id],
            ], $completionKey, $actor);
            $order->update(['completed_quantity' => $after, 'completed_cost' => bcadd((string) $order->completed_cost, $completionCost, 2), 'status' => bccomp($after, (string) $order->planned_quantity, 4) === 0 ? 'completed' : 'in_progress']);
            $this->audit->record($order->company_id, $actor->id, 'manufacturing.production_completed', $order);

            return $order->refresh();
        }, 3);
    }

    public function recordOperation(ProductionOrder $order, RoutingOperation $operation, array $data, User $actor): ProductionOperationLog
    {
        return DB::transaction(function () use ($order, $operation, $data, $actor) {
            if ($existing = ProductionOperationLog::where('company_id', $order->company_id)->where('idempotency_key', $data['idempotency_key'])->first()) {
                return $existing;
            }
            $order = ProductionOrder::lockForUpdate()->findOrFail($order->id);
            $operation = RoutingOperation::with('workCenter')->where('company_id', $order->company_id)->where('bill_of_material_id', $order->bill_of_material_id)->findOrFail($operation->id);
            throw_unless(in_array($order->status, ['released', 'in_progress'], true), ValidationException::withMessages(['order' => 'Production order belum aktif atau sudah selesai.']));
            $laborCost = bcmul((string) $data['actual_hours'], (string) $operation->workCenter->labor_rate_per_hour, 2);
            $overheadCost = bcmul((string) $data['actual_hours'], (string) $operation->workCenter->overhead_rate_per_hour, 2);
            $total = bcadd($laborCost, $overheadCost, 2);
            throw_if(bccomp($total, '0', 2) <= 0, ValidationException::withMessages(['rate' => 'Work center harus mempunyai tarif tenaga kerja atau overhead.']));
            $maps = $this->mappings($order->company_id, 'production_conversion_cost', ['wip_debit', 'labor_absorption_credit', 'overhead_absorption_credit']);
            $lines = [['account_id' => $maps['wip_debit']->account_id, 'debit' => $total, 'credit' => '0', 'project_id' => $order->project_id]];
            if (bccomp($laborCost, '0', 2) === 1) {
                $lines[] = ['account_id' => $maps['labor_absorption_credit']->account_id, 'debit' => '0', 'credit' => $laborCost, 'project_id' => $order->project_id];
            }
            if (bccomp($overheadCost, '0', 2) === 1) {
                $lines[] = ['account_id' => $maps['overhead_absorption_credit']->account_id, 'debit' => '0', 'credit' => $overheadCost, 'project_id' => $order->project_id];
            }
            $journal = $this->accounting->post($order->company_id, now()->toDateString(), 'production_conversion_cost', (string) $order->id, 'Biaya operasi '.$operation->name.' / '.$order->number, $lines, 'production-operation:'.$data['idempotency_key'], $actor);
            $log = ProductionOperationLog::create([...$data, 'company_id' => $order->company_id, 'production_order_id' => $order->id, 'routing_operation_id' => $operation->id, 'labor_cost' => $laborCost, 'overhead_cost' => $overheadCost, 'performed_at' => now(), 'recorded_by' => $actor->id, 'journal_id' => $journal->id]);
            $order->update(['status' => 'in_progress', 'actual_labor_cost' => bcadd((string) $order->actual_labor_cost, $laborCost, 2), 'actual_overhead_cost' => bcadd((string) $order->actual_overhead_cost, $overheadCost, 2)]);
            $this->audit->record($order->company_id, $actor->id, 'manufacturing.operation_recorded', $log);

            return $log;
        }, 3);
    }

    public function inspect(ProductionOrder $order, array $data, User $actor): ProductionInspection
    {
        return DB::transaction(function () use ($order, $data, $actor) {
            $order = ProductionOrder::lockForUpdate()->findOrFail($order->id);
            throw_unless(in_array($order->status, ['in_progress', 'released'], true), ValidationException::withMessages(['order' => 'Order belum siap untuk inspeksi.']));
            $accepted = (string) ProductionInspection::where('production_order_id', $order->id)->where('result', 'accepted')->sum('inspected_quantity');
            $scrapped = (string) ProductionDisposition::whereHas('inspection', fn ($query) => $query->where('production_order_id', $order->id))->where('disposition', 'scrap')->sum('quantity');
            throw_if(bccomp(bcadd(bcadd($accepted, $scrapped, 4), (string) $data['inspected_quantity'], 4), (string) $order->planned_quantity, 4) === 1, ValidationException::withMessages(['quantity' => 'Kuantitas inspeksi melebihi sisa output produksi.']));
            $inspection = ProductionInspection::create([...$data, 'company_id' => $order->company_id, 'production_order_id' => $order->id, 'inspected_by' => $actor->id, 'inspected_at' => now()]);
            $this->audit->record($order->company_id, $actor->id, 'manufacturing.output_inspected', $inspection);

            return $inspection;
        }, 3);
    }

    public function dispose(ProductionInspection $inspection, array $data, User $actor): ProductionDisposition
    {
        return DB::transaction(function () use ($inspection, $data, $actor) {
            if ($existing = ProductionDisposition::where('company_id', $inspection->company_id)->where('idempotency_key', $data['idempotency_key'])->first()) {
                return $existing;
            }
            $inspection = ProductionInspection::with('productionOrder')->lockForUpdate()->findOrFail($inspection->id);
            throw_unless($inspection->result === 'rejected', ValidationException::withMessages(['inspection' => 'Disposition hanya untuk hasil inspeksi ditolak.']));
            $disposed = (string) ProductionDisposition::where('production_inspection_id', $inspection->id)->sum('quantity');
            throw_if(bccomp(bcadd($disposed, (string) $data['quantity'], 4), (string) $inspection->inspected_quantity, 4) === 1, ValidationException::withMessages(['quantity' => 'Kuantitas disposition melebihi kuantitas ditolak.']));

            $journalId = null;
            $costAmount = '0';
            if ($data['disposition'] === 'scrap') {
                $order = $inspection->productionOrder;
                $actualCost = bcadd(bcadd((string) $order->actual_material_cost, (string) $order->actual_labor_cost, 2), (string) $order->actual_overhead_cost, 2);
                $previousScrapQuantity = (string) ProductionDisposition::whereHas('inspection', fn ($query) => $query->where('production_order_id', $order->id))->where('disposition', 'scrap')->sum('quantity');
                $previousScrapCost = (string) ProductionDisposition::whereHas('inspection', fn ($query) => $query->where('production_order_id', $order->id))->where('disposition', 'scrap')->sum('cost_amount');
                $accountedAfter = bcadd(bcadd((string) $order->completed_quantity, $previousScrapQuantity, 4), (string) $data['quantity'], 4);
                $amount = bccomp($accountedAfter, (string) $order->planned_quantity, 4) >= 0
                    ? bcsub(bcsub($actualCost, (string) $order->completed_cost, 2), $previousScrapCost, 2)
                    : bcdiv(bcmul($actualCost, (string) $data['quantity'], 4), (string) $order->planned_quantity, 2);
                throw_if(bccomp($amount, '0', 2) <= 0, ValidationException::withMessages(['cost' => 'Biaya scrap tidak dapat ditentukan.']));
                $maps = $this->mappings($inspection->company_id, 'production_scrap', ['scrap_expense_debit', 'wip_credit']);
                $journal = $this->accounting->post($inspection->company_id, now()->toDateString(), 'production_scrap', (string) $inspection->id, 'Scrap produksi '.$order->number, [
                    ['account_id' => $maps['scrap_expense_debit']->account_id, 'debit' => $amount, 'credit' => '0', 'project_id' => $order->project_id],
                    ['account_id' => $maps['wip_credit']->account_id, 'debit' => '0', 'credit' => $amount, 'project_id' => $order->project_id],
                ], 'production-scrap:'.$data['idempotency_key'], $actor);
                $journalId = $journal->id;
                $costAmount = $amount;
            }
            $disposition = ProductionDisposition::create([...$data, 'company_id' => $inspection->company_id, 'production_inspection_id' => $inspection->id, 'cost_amount' => $costAmount, 'decided_by' => $actor->id, 'decided_at' => now(), 'journal_id' => $journalId]);
            if ($data['disposition'] === 'scrap') {
                $scrapped = (string) ProductionDisposition::whereHas('inspection', fn ($query) => $query->where('production_order_id', $inspection->production_order_id))->where('disposition', 'scrap')->sum('quantity');
                if (bccomp(bcadd((string) $inspection->productionOrder->completed_quantity, $scrapped, 4), (string) $inspection->productionOrder->planned_quantity, 4) >= 0) {
                    $inspection->productionOrder->update(['status' => 'completed_with_scrap']);
                }
            }
            $this->audit->record($inspection->company_id, $actor->id, 'manufacturing.rejected_output_disposed', $disposition);

            return $disposition;
        }, 3);
    }

    private function mappings(int $companyId, string $event, array $sides)
    {
        $maps = AccountingMapping::where('company_id', $companyId)->where('event_type', $event)->get()->keyBy('entry_side');
        foreach ($sides as $side) {
            throw_unless($maps->has($side), ValidationException::withMessages(['mapping' => "Mapping $event/$side belum tersedia."]));
        }

        return $maps;
    }
}
