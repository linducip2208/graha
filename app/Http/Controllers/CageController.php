<?php

namespace App\Http\Controllers;

use App\Models\BoredPile;
use App\Models\CompanySetting;
use App\Models\FieldEvidence;
use App\Models\Item;
use App\Models\ReinforcementCage;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Services\FieldOpsService;
use App\Services\ReinforcementCageService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class CageController extends Controller
{
    public function index(Request $request, CurrentCompany $current)
    {
        $companyId = $current->id();
        $cages = ReinforcementCage::where('company_id', $companyId)->with(['pile.project'])->orderByDesc('id')->limit(50)->get();

        return view('manufacturing.cages', [
            'cages' => $cages,
            'piles' => BoredPile::whereHas('project', fn ($q) => $q->where('company_id', $companyId))->whereIn('status', ['cleaning', 'inspection', 'cage_installation'])->with(['project', 'zone'])->get(),
            'tolerance' => CompanySetting::val($companyId, 'steel_variance_tolerance_percent'),
            'items' => Item::where('company_id', $companyId)->orderBy('name')->get(),
            'warehouses' => Warehouse::where('company_id', $companyId)->with('bins')->orderBy('code')->get(),
            'evidences' => FieldEvidence::where('company_id', $companyId)->where('evidence_type', 'cage')
                ->whereIn('evidence_id', ReinforcementCage::where('company_id', $companyId)->select('id'))
                ->with('uploader')->latest()->get()->groupBy(fn ($e) => $e->evidence_id),
        ]);
    }

    public function store(Request $request, CurrentCompany $current, ReinforcementCageService $service)
    {
        $data = $request->validate([
            'number' => ['required', 'max:80'],
            'design_ref' => ['nullable', 'max:120'],
            'diameter_mm' => ['required', 'decimal:0,2', 'gt:0'],
            'total_length_m' => ['required', 'decimal:0,3', 'gt:0'],
            'segment_count' => ['nullable', 'integer', 'min:1'],
            'main_bar_spec' => ['nullable', 'max:80'],
            'spiral_spec' => ['nullable', 'max:80'],
            'stiffener_spec' => ['nullable', 'max:80'],
            'coupler_count' => ['nullable', 'integer', 'min:0'],
            'theoretical_weight_kg' => ['nullable', 'decimal:0,2'],
            'heat_number' => ['nullable', 'max:80'],
            'mill_cert_number' => ['nullable', 'max:80'],
            'storage_location' => ['nullable', 'max:150'],
            'notes' => ['nullable', 'max:500'],
        ]);
        $service->create($current->id(), $data, $request->user());

        return back()->with('status', 'Cage didaftarkan — menunggu QC oleh pemeriksa lain.');
    }

    public function qc(Request $request, ReinforcementCage $cage, CurrentCompany $current, ReinforcementCageService $service)
    {
        abort_unless($cage->company_id === $current->id(), 404);
        $data = $request->validate([
            'result' => ['required', 'in:passed,failed'],
            'actual_weight_kg' => ['nullable', 'decimal:0,2', 'gt:0'],
            'qc_notes' => ['nullable', 'max:1000'],
        ]);
        if (! empty($data['actual_weight_kg'])) {
            $cage->update(['actual_weight_kg' => $data['actual_weight_kg']]);
        }
        $service->recordQc($cage->refresh(), $data['result'] === 'passed', $data['qc_notes'] ?? null, $request->user());

        return back()->with('status', 'QC cage direkam.');
    }

    public function deliver(Request $request, ReinforcementCage $cage, CurrentCompany $current, ReinforcementCageService $service)
    {
        abort_unless($cage->company_id === $current->id(), 404);
        $data = $request->validate(['bored_pile_id' => ['required', 'integer']]);
        $pile = BoredPile::whereHas('project', fn ($q) => $q->where('company_id', $current->id()))->findOrFail($data['bored_pile_id']);
        $service->deliverToPile($cage->refresh(), $pile, $request->user());

        return back()->with('status', 'Cage dikirim ke titik '.$pile->pile_number.'.');
    }

    /** Foto evidence cage (proses fabrikasi, timbangan, QC, pengiriman). */
    public function uploadEvidence(Request $request, ReinforcementCage $cage, CurrentCompany $current, FieldOpsService $service)
    {
        abort_unless($cage->company_id === $current->id(), 404);
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $evidence = $service->storeEvidence('cage', $cage->id, $data['file'], $request->user());

        return back()->with('status', "Foto cage terlampir (#{$evidence->id}).");
    }

    /** Bebankan material baja fabrikasi ke cage: stock ledger + jurnal otomatis. */
    public function consumeMaterial(Request $request, ReinforcementCage $cage, CurrentCompany $current, ReinforcementCageService $service)
    {
        abort_unless($cage->company_id === $current->id(), 404);
        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'warehouse_bin_id' => ['required', 'integer'],
            'quantity_kg' => ['required', 'decimal:0,4', 'gt:0'],
            'lot_number' => ['nullable', 'max:80'],
            'unit_cost' => ['nullable', 'decimal:0,4', 'min:0'],
            'idempotency_key' => ['required', 'max:120'],
        ]);
        abort_unless(Item::where('company_id', $current->id())->whereKey($data['item_id'])->exists(), 422);
        $bin = WarehouseBin::whereHas('warehouse', fn ($q) => $q->where('company_id', $current->id()))->findOrFail($data['warehouse_bin_id']);
        $data['warehouse_id'] = $bin->warehouse_id;
        $service->consumeMaterial($cage->refresh(), $data, $request->user());

        return back()->with('status', "Material {$data['quantity_kg']} kg dibebankan ke cage {$cage->number}: stok dan jurnal tercatat.");
    }
}
