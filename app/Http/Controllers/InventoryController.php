<?php

namespace App\Http\Controllers;

use App\Models\BoredPile;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Services\InventoryService;
use App\Services\ReorderService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /** Lot traceability (ADR-056): telusuri satu lot dari receipt hingga konsumsi. */
    public function lotTrace(Request $request, CurrentCompany $current)
    {
        $companyId = $current->id();
        $lot = trim((string) $request->query('lot'));
        $movements = collect();
        if ($lot !== '') {
            $movements = StockMovement::where('company_id', $companyId)
                ->where('lot_number', $lot)
                ->with(['item:id,sku,name', 'warehouse:id,code', 'bin:id,code'])
                ->orderBy('id')->limit(200)->get()
                ->map(function (StockMovement $m): StockMovement {
                    $m->setAttribute('source_label', match ($m->reference_type) {
                        'goods_receipt' => 'GR #'.$m->reference_id,
                        'material_request' => 'Material Request #'.$m->reference_id,
                        'production_order' => 'Production Order #'.$m->reference_id,
                        'reinforcement_cage' => 'Cage #'.$m->reference_id,
                        'warehouse_transfer' => 'Transfer '.$m->reference_id,
                        default => ucfirst(str_replace('_', ' ', (string) $m->reference_type)),
                    });
                    $m->setAttribute('pile_number', $m->bored_pile_id ? BoredPile::find($m->bored_pile_id)?->pile_number : null);

                    return $m;
                });
        }

        return view('inventory.lot-trace', ['lot' => $lot, 'movements' => $movements]);
    }

    public function index(CurrentCompany $current)
    {
        $lowStock = StockBalance::where('stock_balances.company_id', $current->id())->join('items', 'items.id', '=', 'stock_balances.item_id')->whereColumn('stock_balances.quantity', '<=', 'items.minimum_stock')->select('stock_balances.*')->with(['item', 'warehouse', 'bin'])->get();
        $balances = StockBalance::where('company_id', $current->id())->with(['item', 'warehouse', 'bin']);
        if ($term = trim((string) $request->query('q'))) {
            $balances->whereHas('item', fn ($iq) => $iq->where(fn ($w) => $w->where('sku', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")));
        }

        return view('inventory.index', ['items' => Item::where('company_id', $current->id())->orderBy('name')->get(), 'warehouses' => Warehouse::where('company_id', $current->id())->with('bins')->get(), 'balances' => (clone $balances)->paginate(30), 'movements' => StockMovement::where('company_id', $current->id())->with('item')->latest('posted_at')->limit(20)->get(), 'lowStock' => $lowStock, 'balanceCount' => $balances->count()]);
    }

    /** Rekomendasi reorder deterministik dari saldo + outstanding PO (ADR-074). */
    public function reorder(CurrentCompany $current, ReorderService $service)
    {
        return view('inventory.reorder', [
            'recommendations' => $service->recommendations($current->id()),
            'items' => Item::where('company_id', $current->id())->orderBy('sku')->get(),
        ]);
    }

    /** Update parameter reorder satu item. */
    public function updateReorderSettings(Request $r, Item $item, CurrentCompany $current)
    {
        abort_unless($item->company_id === $current->id(), 404);
        $d = $r->validate([
            'reorder_point' => ['required', 'decimal:0,4', 'min:0'],
            'reorder_max' => ['nullable', 'decimal:0,4', 'min:0'],
            'minimum_stock' => ['nullable', 'decimal:0,4', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);
        $item->update([
            'reorder_point' => $d['reorder_point'],
            'reorder_max' => $d['reorder_max'] ?? $item->reorder_max,
            'minimum_stock' => $d['minimum_stock'] ?? $item->minimum_stock,
            'lead_time_days' => $d['lead_time_days'] ?? $item->lead_time_days,
        ]);

        return back()->with('status', "Parameter reorder {$item->sku} disimpan.");
    }

    public function setup(Request $r, CurrentCompany $current)
    {
        $d = $r->validate(['sku' => ['required', 'max:60', 'unique:items,sku,NULL,id,company_id,'.$current->id()], 'name' => ['required', 'max:200'], 'category' => ['required', 'max:60'], 'uom' => ['required', 'max:20'], 'warehouse_code' => ['required', 'max:30'], 'warehouse_name' => ['required', 'max:150'], 'bin_code' => ['required', 'max:40']]);
        $unit = Unit::firstOrCreate(['company_id' => $current->id(), 'code' => $d['uom']], ['name' => $d['uom']]);
        Item::create(['company_id' => $current->id(), 'unit_id' => $unit->id, 'sku' => $d['sku'], 'name' => $d['name'], 'category' => $d['category']]);
        $warehouse = Warehouse::firstOrCreate(['company_id' => $current->id(), 'code' => $d['warehouse_code']], ['name' => $d['warehouse_name']]);
        WarehouseBin::firstOrCreate(['warehouse_id' => $warehouse->id, 'code' => $d['bin_code']], ['name' => $d['bin_code']]);

        return back()->with('status', 'Master inventory disimpan.');
    }

    public function movement(Request $r, CurrentCompany $current, InventoryService $service)
    {
        $d = $r->validate(['item_id' => ['required', 'exists:items,id'], 'warehouse_bin_id' => ['required', 'exists:warehouse_bins,id'], 'movement_type' => ['required', 'in:receipt,issue,return_in,adjustment_in,adjustment_out'], 'quantity' => ['required', 'decimal:0,4', 'gt:0'], 'unit_cost' => ['nullable', 'decimal:0,4', 'gte:0'], 'reference_id' => ['required', 'max:80'], 'reason' => ['nullable', 'max:500']]);
        $item = Item::where('company_id', $current->id())->findOrFail($d['item_id']);
        $bin = WarehouseBin::with('warehouse')->findOrFail($d['warehouse_bin_id']);
        abort_unless($bin->warehouse->company_id === $current->id(), 422);
        $service->post(['company_id' => $current->id(), 'item_id' => $item->id, 'warehouse_id' => $bin->warehouse_id, 'warehouse_bin_id' => $bin->id], $d['movement_type'], $d['quantity'], 'ui:'.$d['movement_type'].':'.$d['reference_id'], $r->user(), ['type' => 'manual_inventory', 'id' => $d['reference_id'], 'reason' => $d['reason'] ?? null], $d['unit_cost'] ?? '0');

        return back()->with('status', 'Movement berhasil diposting.');
    }
}
