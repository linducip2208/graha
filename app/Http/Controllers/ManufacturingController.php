<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\BillOfMaterial;
use App\Models\Item;
use App\Models\ProductionOrder;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Services\ManufacturingService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class ManufacturingController extends Controller
{
    public function index(CurrentCompany $current)
    {
        $id = $current->id();

        return view('manufacturing.index', [
            'items' => Item::where('company_id', $id)->orderBy('name')->get(),
            'warehouses' => Warehouse::where('company_id', $id)->with('bins')->orderBy('code')->get(),
            'boms' => BillOfMaterial::where('company_id', $id)->with(['items.item', 'outputItem'])->latest()->get(),
            'orders' => ProductionOrder::where('company_id', $id)->with(['bom.outputItem', 'materialIssues.item'])->latest()->get(),
            'accounts' => Account::where('company_id', $id)->where('is_active', true)->orderBy('code')->get(),
            'mappings' => AccountingMapping::where('company_id', $id)->whereIn('event_type', ['material_issue_manufacturing', 'production_completion'])->with('account')->get(),
        ]);
    }

    public function addBomItem(Request $request, BillOfMaterial $bom, CurrentCompany $current)
    {
        abort_unless($bom->company_id === $current->id(), 404);
        $data = $request->validate(['item_id' => ['required', 'integer'], 'quantity' => ['required', 'decimal:0,4', 'gt:0'], 'scrap_percent' => ['required', 'decimal:0,3', 'between:0,100']]);
        abort_unless(Item::where('company_id', $current->id())->whereKey($data['item_id'])->exists(), 422);
        abort_if($bom->output_item_id === (int) $data['item_id'], 422, 'Output BOM tidak boleh menjadi komponennya sendiri.');
        $bom->items()->updateOrCreate(['item_id' => $data['item_id']], ['quantity' => $data['quantity'], 'scrap_percent' => $data['scrap_percent']]);

        return back()->with('status', 'Komponen BOM ditambahkan.');
    }

    public function issue(Request $request, ProductionOrder $order, CurrentCompany $current, ManufacturingService $service)
    {
        abort_unless($order->company_id === $current->id(), 404);
        $data = $request->validate(['item_id' => ['required', 'integer'], 'warehouse_bin_id' => ['required', 'integer'], 'quantity' => ['required', 'decimal:0,4', 'gt:0'], 'lot_number' => ['nullable', 'max:80'], 'idempotency_key' => ['required', 'max:120']]);
        $item = Item::where('company_id', $current->id())->findOrFail($data['item_id']);
        $bin = WarehouseBin::whereKey($data['warehouse_bin_id'])->whereHas('warehouse', fn ($query) => $query->where('company_id', $current->id()))->firstOrFail();
        abort_unless($order->bom->items()->where('item_id', $item->id)->exists(), 422, 'Material tidak terdaftar pada BOM order ini.');
        $service->issueMaterial($order, $item, ['warehouse_id' => $bin->warehouse_id, 'warehouse_bin_id' => $bin->id, 'lot_number' => $data['lot_number'] ?? '', 'project_id' => $order->project_id], $data['quantity'], $request->user(), $data['idempotency_key']);

        return back()->with('status', 'Material dikeluarkan ke WIP dan jurnal otomatis diposting.');
    }
}
