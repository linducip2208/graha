<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockBalance;
use Illuminate\Support\Facades\DB;

/**
 * Rekomendasi reorder deterministik (ADR-074): sinyal dari data nyata.
 * Trigger: saldo on-hand <= reorder_point (item aktif, reorder_point > 0).
 * Target: reorder_max bila diisi, jika tidak max(reorder_point, minimum_stock).
 * On-order = sisa outstanding PO (ordered - received) pada PO yang belum
 * dibatalkan/ditolak/ditutup. Usulan <= 0 tidak direkomendasikan.
 */
class ReorderService
{
    public function recommendations(int $companyId): array
    {
        $onHand = StockBalance::where('company_id', $companyId)
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(quantity) as total')
            ->pluck('total', 'item_id');

        $onOrder = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_orders.company_id', $companyId)
            ->whereIn('purchase_orders.status', ['draft', 'pending_approval', 'approved', 'active'])
            ->groupBy('purchase_order_items.item_id')
            ->selectRaw('purchase_order_items.item_id, SUM(purchase_order_items.quantity - purchase_order_items.received_quantity) as outstanding')
            ->pluck('outstanding', 'item_id');

        return Item::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('reorder_point', '>', 0)
            ->orderBy('sku')
            ->get()
            ->map(function (Item $item) use ($onHand, $onOrder): ?array {
                $hand = bcadd((string) ($onHand[$item->id] ?? '0'), '0', 4);
                $ordered = bcadd((string) ($onOrder[$item->id] ?? '0'), '0', 4);
                if (bccomp($hand, (string) $item->reorder_point, 4) === 1) {
                    return null;
                }
                $target = bccomp((string) $item->reorder_max, '0', 4) === 1
                    ? (string) $item->reorder_max
                    : (bccomp((string) $item->minimum_stock, (string) $item->reorder_point, 4) === 1 ? (string) $item->minimum_stock : (string) $item->reorder_point);
                $suggested = bcsub(bcsub($target, $hand, 4), $ordered, 4);
                if (bccomp($suggested, '0', 4) !== 1) {
                    return null;
                }

                return [
                    'item' => $item,
                    'on_hand' => $hand,
                    'on_order' => $ordered,
                    'target' => $target,
                    'suggested_qty' => $suggested,
                    'lead_time_days' => (int) $item->lead_time_days,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
