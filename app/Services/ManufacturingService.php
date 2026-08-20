<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ProductionMaterialIssue;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManufacturingService
{
    public function __construct(private InventoryService $inventory, private AuditTrail $audit) {}

    public function issueMaterial(ProductionOrder $order, Item $item, array $dimension, string $quantity, User $actor, string $key): ProductionMaterialIssue
    {
        return DB::transaction(function () use ($order, $item, $dimension, $quantity, $actor, $key) {
            $order = ProductionOrder::lockForUpdate()->findOrFail($order->id);
            throw_unless(in_array($order->status, ['planned', 'released', 'in_progress'], true), ValidationException::withMessages(['order' => 'Production order tidak aktif.']));
            $movement = $this->inventory->post([...$dimension, 'company_id' => $order->company_id, 'item_id' => $item->id], 'issue', $quantity, 'production:'.$order->id.':'.$key, $actor, ['type' => 'production_order', 'id' => $order->id]);
            $issue = ProductionMaterialIssue::firstOrCreate(['stock_movement_id' => $movement->id], ['production_order_id' => $order->id, 'item_id' => $item->id, 'quantity' => $quantity, 'lot_number' => $dimension['lot_number'] ?? '']);
            $cost = bcmul($quantity, (string) $movement->unit_cost, 2);
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
            $this->inventory->post(['company_id' => $order->company_id, 'item_id' => $order->bom->output_item_id, 'warehouse_id' => $order->warehouse_id, 'warehouse_bin_id' => $order->output_bin_id], 'receipt', $quantity, 'production-complete:'.$order->id.':'.$key, $actor, ['type' => 'production_order', 'id' => $order->id]);
            $order->update(['completed_quantity' => $after, 'status' => bccomp($after, (string) $order->planned_quantity, 4) === 0 ? 'completed' : 'in_progress']);
            $this->audit->record($order->company_id,$actor->id,'manufacturing.production_completed',$order);

            return $order->refresh();
        }, 3);
    }
}
