<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\BillOfMaterial;
use App\Models\Item;
use App\Models\ProductionInspection;
use App\Models\ProductionOrder;
use App\Models\RoutingOperation;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\WorkCenter;
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
            'orders' => ProductionOrder::where('company_id', $id)->with(['bom.outputItem', 'materialIssues.item', 'inspections'])->latest()->get(),
            'accounts' => Account::where('company_id', $id)->where('is_active', true)->orderBy('code')->get(),
            'mappings' => AccountingMapping::where('company_id', $id)->whereIn('event_type', ['material_issue_manufacturing', 'production_completion'])->with('account')->get(),
        ]);
    }

    public function quality(CurrentCompany $current)
    {
        return view('manufacturing.quality', ['orders' => ProductionOrder::where('company_id', $current->id())->with(['bom.outputItem', 'inspections'])->latest()->get()]);
    }

    public function nonconforming(CurrentCompany $current)
    {
        $id = $current->id();

        return view('manufacturing.nonconforming', ['inspections' => ProductionInspection::where('company_id', $id)->where('result', 'rejected')->with(['productionOrder.bom.outputItem', 'dispositions'])->latest('inspected_at')->get(), 'accounts' => Account::where('company_id', $id)->where('is_active', true)->orderBy('code')->get(), 'mappings' => AccountingMapping::where('company_id', $id)->where('event_type', 'production_scrap')->with('account')->get()]);
    }

    public function costing(CurrentCompany $current)
    {
        $id = $current->id();

        return view('manufacturing.costing', [
            'workCenters' => WorkCenter::where('company_id', $id)->orderBy('code')->get(),
            'boms' => BillOfMaterial::where('company_id', $id)->with(['outputItem', 'routingOperations.workCenter'])->orderBy('code')->get(),
            'orders' => ProductionOrder::where('company_id', $id)->with(['bom.routingOperations.workCenter', 'operationLogs.routingOperation.workCenter'])->latest()->get(),
            'accounts' => Account::where('company_id', $id)->where('is_active', true)->orderBy('code')->get(),
            'mappings' => AccountingMapping::where('company_id', $id)->where('event_type', 'production_conversion_cost')->with('account')->get(),
        ]);
    }

    public function workCenter(Request $request, CurrentCompany $current)
    {
        $data = $request->validate(['code' => ['required', 'max:50'], 'name' => ['required', 'max:255'], 'labor_rate_per_hour' => ['required', 'decimal:0,2', 'min:0'], 'overhead_rate_per_hour' => ['required', 'decimal:0,2', 'min:0']]);
        abort_if(bccomp($data['labor_rate_per_hour'], '0', 2) === 0 && bccomp($data['overhead_rate_per_hour'], '0', 2) === 0, 422, 'Minimal satu tarif harus lebih besar dari nol.');
        WorkCenter::create([...$data, 'company_id' => $current->id()]);

        return back()->with('status', 'Work center dan tarif biaya dibuat.');
    }

    public function routingOperation(Request $request, BillOfMaterial $bom, CurrentCompany $current)
    {
        abort_unless($bom->company_id === $current->id(), 404);
        $data = $request->validate(['work_center_id' => ['required', 'integer'], 'sequence' => ['required', 'integer', 'min:1'], 'name' => ['required', 'max:255'], 'standard_minutes_per_unit' => ['required', 'decimal:0,4', 'gt:0'], 'work_instruction' => ['nullable', 'max:2000']]);
        abort_unless(WorkCenter::where('company_id', $current->id())->whereKey($data['work_center_id'])->exists(), 422);
        RoutingOperation::updateOrCreate(['bill_of_material_id' => $bom->id, 'sequence' => $data['sequence']], [...$data, 'company_id' => $current->id()]);

        return back()->with('status', 'Tahap routing produksi disimpan.');
    }

    public function recordOperation(Request $request, ProductionOrder $order, RoutingOperation $operation, CurrentCompany $current, ManufacturingService $service)
    {
        abort_unless($order->company_id === $current->id() && $operation->company_id === $current->id(), 404);
        $data = $request->validate(['quantity_processed' => ['required', 'decimal:0,4', 'gt:0'], 'actual_hours' => ['required', 'decimal:0,4', 'gt:0'], 'notes' => ['nullable', 'max:2000'], 'idempotency_key' => ['required', 'max:120']]);
        $service->recordOperation($order, $operation, $data, $request->user());

        return back()->with('status', 'Realisasi operasi dan biaya konversi berhasil diposting ke WIP.');
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

    public function inspect(Request $request, ProductionOrder $order, CurrentCompany $current, ManufacturingService $service)
    {
        abort_unless($order->company_id === $current->id(), 404);
        $data = $request->validate(['number' => ['required', 'max:80'], 'inspected_quantity' => ['required', 'decimal:0,4', 'gt:0'], 'result' => ['required', 'in:accepted,rejected'], 'criteria' => ['required', 'max:2000'], 'findings' => ['nullable', 'max:2000'], 'evidence_reference' => ['nullable', 'max:255']]);
        $service->inspect($order, $data, $request->user());

        return back()->with('status', 'Hasil inspeksi produksi dicatat sebagai release gate.');
    }

    public function dispose(Request $request, ProductionInspection $inspection, CurrentCompany $current, ManufacturingService $service)
    {
        abort_unless($inspection->company_id === $current->id(), 404);
        $data = $request->validate(['number' => ['required', 'max:80'], 'disposition' => ['required', 'in:rework,scrap'], 'quantity' => ['required', 'decimal:0,4', 'gt:0'], 'reason' => ['required', 'max:2000'], 'instruction' => ['nullable', 'max:2000'], 'idempotency_key' => ['required', 'max:120']]);
        $service->dispose($inspection, $data, $request->user());

        return back()->with('status', 'Disposition output ditolak berhasil dicatat.');
    }
}
