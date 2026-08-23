<?php

namespace App\Http\Controllers;

use App\Models\BoredPile;
use App\Models\CompanySetting;
use App\Models\FieldEvidence;
use App\Models\ReinforcementCage;
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
}
