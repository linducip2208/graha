<?php

namespace App\Http\Controllers;

use App\Models\BillOfMaterial;
use App\Models\Equipment;
use App\Models\FuelUsage;
use App\Models\Item;
use App\Models\MaintenanceWorkOrder;
use App\Models\ProductionOrder;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Services\EquipmentService;
use App\Services\ManufacturingService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class OperationsController extends Controller
{
    public function index(CurrentCompany $current)
    {
        $id = $current->id();

        return view('operations.index', ['items' => Item::where('company_id', $id)->orderBy('name')->get(), 'warehouses' => Warehouse::where('company_id', $id)->with('bins')->get(), 'boms' => BillOfMaterial::where('company_id', $id)->with('items')->latest()->get(), 'orders' => ProductionOrder::where('company_id', $id)->with('bom')->latest()->get(), 'equipment' => Equipment::where('company_id', $id)->orderBy('code')->get(), 'workOrders' => MaintenanceWorkOrder::where('company_id', $id)->latest()->limit(50)->get(), 'fuelUsages' => FuelUsage::where('company_id', $id)->with('equipment')->latest('used_at')->limit(50)->get()]);
    }

    public function bom(Request $request, CurrentCompany $current)
    {
        $data = $request->validate(['code' => ['required', 'max:50'], 'version' => ['required', 'integer', 'min:1'], 'output_item_id' => ['required', 'exists:items,id'], 'output_quantity' => ['required', 'decimal:0,4', 'gt:0']]);
        abort_unless(Item::where('company_id', $current->id())->whereKey($data['output_item_id'])->exists(), 422);
        BillOfMaterial::create([...$data, 'company_id' => $current->id(), 'status' => 'active']);

        return back()->with('status', 'BOM dibuat.');
    }

    public function productionOrder(Request $request, CurrentCompany $current)
    {
        $data = $request->validate(['number' => ['required', 'max:80'], 'bill_of_material_id' => ['required', 'exists:bills_of_material,id'], 'warehouse_id' => ['required', 'exists:warehouses,id'], 'output_bin_id' => ['required', 'exists:warehouse_bins,id'], 'planned_quantity' => ['required', 'decimal:0,4', 'gt:0']]);
        abort_unless(BillOfMaterial::where('company_id', $current->id())->whereKey($data['bill_of_material_id'])->exists(), 422);
        abort_unless(Warehouse::where('company_id', $current->id())->whereKey($data['warehouse_id'])->exists(), 422);
        abort_unless(WarehouseBin::where('warehouse_id', $data['warehouse_id'])->whereKey($data['output_bin_id'])->exists(), 422);
        ProductionOrder::create([...$data, 'company_id' => $current->id(), 'created_by' => $request->user()->id]);

        return back()->with('status', 'Production order dibuat.');
    }

    public function complete(Request $request, ProductionOrder $order, CurrentCompany $current, ManufacturingService $service)
    {
        abort_unless($order->company_id === $current->id(), 404);
        $data = $request->validate(['quantity' => ['required', 'decimal:0,4', 'gt:0'], 'idempotency_key' => ['required', 'max:120']]);
        $service->complete($order, $data['quantity'], $request->user(), $data['idempotency_key']);

        return back()->with('status', 'Output produksi masuk ke stock ledger.');
    }

    public function equipment(Request $request, CurrentCompany $current)
    {
        $data = $request->validate(['code' => ['required', 'max:50'], 'name' => ['required', 'max:255'], 'ownership' => ['required', 'in:owned,rented'], 'category' => ['required', 'max:50'], 'current_hour_meter' => ['required', 'decimal:0,2', 'min:0'], 'fuel_target_lph' => ['nullable', 'decimal:0,4', 'gt:0']]);
        Equipment::create([...$data, 'company_id' => $current->id()]);

        return back()->with('status', 'Equipment ditambahkan.');
    }

    public function meter(Request $request, Equipment $equipment, CurrentCompany $current, EquipmentService $service)
    {
        abort_unless($equipment->company_id === $current->id(), 404);
        $data = $request->validate(['reading' => ['required', 'decimal:0,2', 'min:0']]);
        $service->recordMeter($equipment, $data['reading'], $request->user());

        return back()->with('status', 'Hour meter dicatat.');
    }

    public function fuel(Request $request, Equipment $equipment, CurrentCompany $current, EquipmentService $service)
    {
        abort_unless($equipment->company_id === $current->id(), 404);
        $data = $request->validate(['liters' => ['required', 'decimal:0,4', 'gt:0'], 'start_meter' => ['required', 'decimal:0,2'], 'end_meter' => ['required', 'decimal:0,2', 'gt:start_meter'], 'reference' => ['required', 'max:80']]);
        $service->recordFuel($equipment, $data['liters'], $data['start_meter'], $data['end_meter'], $data['reference'], $request->user());

        return back()->with('status', 'Fuel dan rasio LPH dicatat.');
    }

    public function maintenance(Request $request, Equipment $equipment, CurrentCompany $current)
    {
        abort_unless($equipment->company_id === $current->id(), 404);
        $data = $request->validate(['number' => ['required', 'max:80'], 'type' => ['required', 'in:preventive,corrective,breakdown'], 'problem' => ['required']]);
        MaintenanceWorkOrder::create([...$data, 'company_id' => $current->id(), 'equipment_id' => $equipment->id, 'meter_reading' => $equipment->current_hour_meter, 'opened_by' => $request->user()->id]);

        return back()->with('status', 'Maintenance work order dibuka.');
    }
}
