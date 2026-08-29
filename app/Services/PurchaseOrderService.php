<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\Document;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Models\VendorInvoice;
use App\Models\WarehouseBin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(private InventoryService $inventory, private AuditTrail $audit) {}

    public function recalculate(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order) {
            $order = PurchaseOrder::with('items')->lockForUpdate()->findOrFail($order->id);
            $total = '0';
            foreach ($order->items as $item) {
                $total = bcadd($total, bcmul((string) $item->quantity, (string) $item->unit_price, 2), 2);
            }$order->update(['total' => $total]);

            return $order->refresh();
        }, 3);
    }

    public function revise(PurchaseOrder $order, array $changes, string $reason, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $changes, $reason, $actor) {
            $order = PurchaseOrder::with('items')->lockForUpdate()->findOrFail($order->id);
            $order->revisions()->create(['version' => $order->version, 'snapshot' => ['order' => $order->toArray(), 'items' => $order->items->toArray()], 'reason' => $reason, 'revised_by' => $actor->id]);
            ApprovalRequest::where('approvable_type', PurchaseOrder::class)->where('approvable_id', $order->id)->whereNotIn('status', ['rejected', 'superseded'])->update(['status' => 'superseded', 'completed_at' => now()]);
            $order->update([...$changes, 'version' => $order->version + 1, 'status' => 'draft', 'revision_reason' => $reason]);
            $this->audit->record($order->company_id, $actor->id, 'procurement.po_revised', $order);

            return $this->recalculate($order);
        }, 3);
    }

    public function receive(PurchaseOrder $order, int $warehouseId, array $lines, string $number, string $key, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($order, $warehouseId, $lines, $number, $key, $actor) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($order->id);
            throw_unless(in_array($order->status, ['approved', 'issued', 'partially_received'], true), ValidationException::withMessages(['order' => 'PO belum dapat diterima.']));
            $existing = GoodsReceipt::where('company_id', $order->company_id)->where('idempotency_key', $key)->first();
            if ($existing) {
                throw_if($existing->purchase_order_id !== $order->id || $existing->warehouse_id !== $warehouseId || $existing->number !== $number, ValidationException::withMessages(['idempotency_key' => 'Kunci penerimaan sudah dipakai untuk dokumen berbeda.']));

                return $existing;
            }$receipt = GoodsReceipt::create(['company_id' => $order->company_id, 'purchase_order_id' => $order->id, 'warehouse_id' => $warehouseId, 'number' => $number, 'received_at' => now(), 'received_by' => $actor->id, 'idempotency_key' => $key]);
            foreach ($lines as $line) {
                $item = PurchaseOrderItem::lockForUpdate()->findOrFail($line['purchase_order_item_id']);
                throw_unless($item->purchase_order_id === $order->id, ValidationException::withMessages(['item' => 'Item bukan milik PO.']));
                throw_unless(WarehouseBin::where('warehouse_id', $warehouseId)->whereKey($line['warehouse_bin_id'])->exists(), ValidationException::withMessages(['warehouse_bin_id' => 'Bin bukan milik gudang penerimaan.']));
                $after = bcadd((string) $item->received_quantity, (string) $line['quantity'], 4);
                throw_if(bccomp($after, (string) $item->quantity, 4) === 1, ValidationException::withMessages(['quantity' => 'Penerimaan melebihi PO.']));
                $movement = $this->inventory->post(['company_id' => $order->company_id, 'item_id' => $item->item_id, 'warehouse_id' => $warehouseId, 'warehouse_bin_id' => $line['warehouse_bin_id']], 'receipt', (string) $line['quantity'], $key.':'.$item->id, $actor, ['type' => 'goods_receipt', 'id' => $receipt->id], (string) $item->unit_price);
                $receipt->items()->create(['purchase_order_item_id' => $item->id, 'warehouse_bin_id' => $line['warehouse_bin_id'], 'quantity' => $line['quantity'], 'stock_movement_id' => $movement->id]);
                $item->update(['received_quantity' => $after]);
            }$order->load('items');
            $complete = $order->items->every(fn ($item) => bccomp((string) $item->received_quantity, (string) $item->quantity, 4) === 0);
            $order->update(['status' => $complete ? 'received' : 'partially_received']);
            $this->audit->record($order->company_id, $actor->id, 'procurement.goods_received', $receipt);

            return $receipt->load('items');
        }, 3);
    }

    public function match(VendorInvoice $invoice): VendorInvoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = VendorInvoice::lockForUpdate()->findOrFail($invoice->id);
            $order = PurchaseOrder::with('items')->findOrFail($invoice->purchase_order_id);
            $qtyMatched = $order->items->every(fn ($item) => bccomp((string) $item->received_quantity, (string) $item->quantity, 4) >= 0);
            $subtotal = $invoice->effectiveSubtotal();
            $amountMatched = bccomp($subtotal, (string) $order->total, 2) === 0;
            $status = $qtyMatched && $amountMatched ? 'matched' : 'exception';
            $shortItems = $order->items->filter(fn ($item) => bccomp((string) $item->received_quantity, (string) $item->quantity, 4) < 0)
                ->map(fn ($item) => ['purchase_order_item_id' => $item->id, 'ordered' => (string) $item->quantity, 'received' => (string) $item->received_quantity])->values()->all();
            $invoice->update(['match_status' => $status, 'match_details' => ['po_total' => $order->total, 'invoice_subtotal' => $subtotal, 'invoice_total' => $invoice->total, 'tax_amount' => $invoice->tax_amount, 'quantity_received' => $qtyMatched, 'amount_difference' => bcsub((string) $order->total, $subtotal, 2), 'quantity_flag' => $qtyMatched ? 'full' : 'short', 'short_items' => $shortItems]]);

            return $invoice->refresh();
        }, 3);
    }

    public function activateApproved(PurchaseOrder $order, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $actor) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($order->id);
            $approved = ApprovalRequest::where('approvable_type', PurchaseOrder::class)->where('approvable_id', $order->id)->where('status', 'approved')->exists();
            throw_unless($approved, ValidationException::withMessages(['approval' => 'PO belum mempunyai approval yang selesai.']));
            $order->update(['status' => 'approved']);
            Document::firstOrCreate(
                ['company_id' => $order->company_id, 'document_type' => 'purchase_order', 'number' => $order->number],
                ['title' => 'PO '.$order->number.' — '.$order->vendor?->name, 'owner_id' => $actor->id, 'workflow_status' => 'approved', 'signature_status' => 'unsigned']
            );
            $this->audit->record($order->company_id, $actor->id, 'procurement.po_activated', $order);

            return $order->refresh();
        }, 3);
    }
}
