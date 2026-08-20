<?php

namespace App\Services;

use App\Models\AccountingMapping;
use App\Models\Item;
use App\Models\ProductionMaterialIssue;
use App\Models\ProductionOrder;
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
            $order = ProductionOrder::with('bom')->lockForUpdate()->findOrFail($order->id);
            throw_if(bccomp($quantity, '0', 4) <= 0, ValidationException::withMessages(['quantity' => 'Output harus positif.']));
            $after = bcadd((string) $order->completed_quantity, $quantity, 4);
            throw_if(bccomp($after, (string) $order->planned_quantity, 4) === 1, ValidationException::withMessages(['quantity' => 'Output melebihi rencana.']));
            $completionCost = bcdiv(bcmul((string) $order->actual_material_cost, $quantity, 4), (string) $order->planned_quantity, 2);
            throw_if(bccomp($completionCost, '0', 2) <= 0, ValidationException::withMessages(['cost' => 'Production order belum mempunyai material cost.']));
            $this->inventory->post(['company_id' => $order->company_id, 'item_id' => $order->bom->output_item_id, 'warehouse_id' => $order->warehouse_id, 'warehouse_bin_id' => $order->output_bin_id], 'receipt', $quantity, 'production-complete:'.$order->id.':'.$key, $actor, ['type' => 'production_order', 'id' => $order->id], bcdiv($completionCost, $quantity, 4));
            $maps = $this->mappings($order->company_id, 'production_completion', ['finished_goods_debit', 'wip_credit']);
            $this->accounting->post($order->company_id, now()->toDateString(), 'production_completion', (string) $order->id, 'Production completion '.$order->number, [
                ['account_id' => $maps['finished_goods_debit']->account_id, 'debit' => $completionCost, 'credit' => '0', 'project_id' => $order->project_id],
                ['account_id' => $maps['wip_credit']->account_id, 'debit' => '0', 'credit' => $completionCost, 'project_id' => $order->project_id],
            ], 'manufacturing-completion:'.$order->id.':'.$key, $actor);
            $order->update(['completed_quantity' => $after, 'status' => bccomp($after, (string) $order->planned_quantity, 4) === 0 ? 'completed' : 'in_progress']);
            $this->audit->record($order->company_id, $actor->id, 'manufacturing.production_completed', $order);

            return $order->refresh();
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
