<?php

namespace App\Http\Controllers;

use App\Models\BoredPile;
use App\Models\CasingUnit;
use App\Models\FieldEvidence;
use App\Services\CasingService;
use App\Services\FieldOpsService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class CasingController extends Controller
{
    public function index(CurrentCompany $current)
    {
        $companyId = $current->id();

        return view('operations.casings', [
            'units' => CasingUnit::where('company_id', $companyId)->with(['movements' => fn ($q) => $q->limit(5), 'currentPile'])->orderBy('code')->get(),
            'piles' => BoredPile::whereHas('project', fn ($q) => $q->where('company_id', $companyId))->whereIn('status', ['setting_out', 'drilling', 'cleaning'])->with('project')->get(),
            'evidences' => FieldEvidence::where('company_id', $companyId)->where('evidence_type', 'casing')
                ->whereIn('evidence_id', CasingUnit::where('company_id', $companyId)->select('id'))
                ->with('uploader')->latest()->get()->groupBy(fn ($e) => $e->evidence_id),
        ]);
    }

    public function store(Request $request, CurrentCompany $current, CasingService $service)
    {
        $data = $request->validate([
            'code' => ['required', 'max:40'],
            'diameter_mm' => ['required', 'decimal:0,2', 'gt:0'],
            'length_m' => ['required', 'decimal:0,3', 'gt:0'],
            'ownership' => ['required', 'in:owned,rented'],
            'supplier_name' => ['nullable', 'max:150'],
            'notes' => ['nullable', 'max:500'],
        ]);
        unset($data['notes']);
        $service->create($current->id(), $data + ['notes' => $request->input('notes')], $request->user());

        return back()->with('status', 'Casing terdaftar.');
    }

    public function move(Request $request, CasingUnit $casing, CurrentCompany $current, CasingService $service)
    {
        abort_unless($casing->company_id === $current->id(), 404);
        $data = $request->validate([
            'type' => ['required', 'in:installed,extracted,left_in_pile,damage_reported,repaired,lost'],
            'bored_pile_id' => ['nullable', 'integer'],
            'cost' => ['nullable', 'decimal:0,2', 'min:0'],
            'notes' => ['nullable', 'max:500'],
        ]);
        if ($data['type'] === 'installed') {
            abort_unless(! empty($data['bored_pile_id']) && BoredPile::whereHas('project', fn ($q) => $q->where('company_id', $current->id()))->whereKey($data['bored_pile_id'])->exists(), 422);
        }
        $service->move($casing->refresh(), $data['type'], $data['bored_pile_id'] ?? null, $data['notes'] ?? null, (float) ($data['cost'] ?? 0), now(), $request->user());

        return back()->with('status', 'Pergerakan casing dicatat.');
    }

    /** Foto evidence casing (kondisi, kerusakan, perbaikan, posisi di titik). */
    public function uploadEvidence(Request $request, CasingUnit $casing, CurrentCompany $current, FieldOpsService $service)
    {
        abort_unless($casing->company_id === $current->id(), 404);
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $evidence = $service->storeEvidence('casing', $casing->id, $data['file'], $request->user());

        return back()->with('status', "Foto casing terlampir (#{$evidence->id}).");
    }
}
