<?php

namespace App\Http\Controllers;

use App\Models\BoredPile;
use App\Models\BoredPileDrilling;
use App\Models\ConcreteDelivery;
use App\Models\FieldEvidence;
use App\Models\PileTest;
use App\Models\Project;
use App\Models\Vendor;
use App\Services\FieldOpsService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FieldOpsController extends Controller
{
    public function index(Request $request, CurrentCompany $current)
    {
        $companyId = $current->id();
        $projects = Project::where('company_id', $companyId)->whereIn('status', ['active', 'in_progress'])->orderBy('code')->get();
        $project = $projects->firstWhere('id', (int) $request->query('project')) ?? $projects->first();
        $piles = collect();
        $drillings = collect();
        $deliveries = collect();
        $tests = collect();
        if ($project) {
            $piles = BoredPile::where('project_id', $project->id)->orderBy('pile_number')->get();
            $drillings = BoredPileDrilling::whereIn('bored_pile_id', $piles->pluck('id'))->with(['pile', 'layers', 'recorder', 'verifier'])->latest()->limit(50)->get();
            $deliveries = ConcreteDelivery::where('project_id', $project->id)->with(['pile', 'vendor'])->latest()->limit(50)->get();
            $tests = PileTest::where('project_id', $project->id)->with('pile')->latest('scheduled_date')->limit(50)->get();
        }

        return view('projects.field-ops', [
            'projects' => $projects,
            'project' => $project,
            'piles' => $piles,
            'drillings' => $drillings,
            'deliveries' => $deliveries,
            'tests' => $tests,
            'vendors' => Vendor::where('company_id', $companyId)->orderBy('name')->get(),
            'testTypes' => PileTest::TYPES,
        ]);
    }

    public function storeDrilling(Request $request, CurrentCompany $current, FieldOpsService $service)
    {
        $data = $request->validate([
            'bored_pile_id' => ['required', 'integer'],
            'drilling_started_at' => ['required', 'date'],
            'drilling_finished_at' => ['nullable', 'date', 'after_or_equal:drilling_started_at'],
            'groundwater_level_m' => ['nullable', 'decimal:0,3'],
            'drilling_tool' => ['nullable', 'max:80'],
            'obstruction' => ['nullable', 'max:1000'],
            'problem' => ['nullable', 'max:1000'],
            'corrective_action' => ['nullable', 'max:1000'],
            'cleaning_method' => ['nullable', 'max:60'],
            'final_cleaning_minutes' => ['nullable', 'integer', 'min:0'],
            'sediment_depth_mm' => ['nullable', 'decimal:0,2'],
            'weather' => ['nullable', 'max:40'],
            'notes' => ['nullable', 'max:2000'],
            'layers' => ['required', 'string'],
        ]);
        $pile = BoredPile::whereHas('project', fn ($q) => $q->where('company_id', $current->id()))->findOrFail($data['bored_pile_id']);
        $layers = $this->parseLayers($data['layers']);
        $service->recordDrilling($pile, collect($data)->except(['bored_pile_id', 'layers'])->all(), $layers, $request->user());

        return back()->with('status', 'Drilling record tersimpan dan menunggu verifikasi.');
    }

    public function verifyDrilling(Request $request, BoredPileDrilling $drilling, CurrentCompany $current, FieldOpsService $service)
    {
        abort_unless($drilling->company_id === $current->id(), 404);
        $service->verifyDrilling($drilling, $request->user());

        return back()->with('status', 'Drilling record diverifikasi.');
    }

    public function storeDelivery(Request $request, CurrentCompany $current, FieldOpsService $service)
    {
        $data = $request->validate([
            'bored_pile_id' => ['required', 'integer'],
            'vendor_id' => ['nullable', 'integer'],
            'batching_plant' => ['nullable', 'max:150'],
            'purchase_order_id' => ['nullable', 'integer'],
            'delivery_order_number' => ['required', 'max:80'],
            'truck_number' => ['required', 'max:40'],
            'driver_name' => ['nullable', 'max:120'],
            'batch_time' => ['nullable', 'date'],
            'arrived_at' => ['nullable', 'date'],
            'pour_started_at' => ['nullable', 'date'],
            'pour_finished_at' => ['nullable', 'date'],
            'grade' => ['nullable', 'max:30'],
            'ordered_volume_m3' => ['required', 'decimal:0,4', 'gt:0'],
            'delivered_volume_m3' => ['required', 'decimal:0,4', 'gt:0'],
            'accepted_volume_m3' => ['required', 'decimal:0,4', 'min:0'],
            'rejected_volume_m3' => ['nullable', 'decimal:0,4', 'min:0'],
            'slump_cm' => ['nullable', 'decimal:0,2'],
            'sample_number' => ['nullable', 'max:60'],
            'idempotency_key' => ['required', 'max:120'],
        ]);
        $pile = BoredPile::whereHas('project', fn ($q) => $q->where('company_id', $current->id()))->findOrFail($data['bored_pile_id']);
        $data['rejected_volume_m3'] ??= '0';
        if (! empty($data['vendor_id'])) {
            abort_unless(Vendor::where('company_id', $current->id())->whereKey($data['vendor_id'])->exists(), 422);
        } else {
            unset($data['vendor_id']);
        }
        $service->recordConcreteDelivery($pile, $data, $request->user());

        return back()->with('status', 'Delivery beton dicatat (draft). Approve untuk memperbarui volume aktual pile.');
    }

    public function approveDelivery(Request $request, ConcreteDelivery $delivery, CurrentCompany $current, FieldOpsService $service)
    {
        abort_unless($delivery->company_id === $current->id(), 404);
        $service->approveConcreteDelivery($delivery, $request->user());

        return back()->with('status', 'Delivery di-approve; volume aktual pile diperbarui.');
    }

    public function rejectDelivery(Request $request, ConcreteDelivery $delivery, CurrentCompany $current, FieldOpsService $service)
    {
        abort_unless($delivery->company_id === $current->id(), 404);
        $data = $request->validate(['rejection_reason' => ['required', 'max:1000']]);
        $service->rejectConcreteDelivery($delivery, $data['rejection_reason'], $request->user());

        return back()->with('status', 'Delivery ditolak.');
    }

    public function storeTest(Request $request, CurrentCompany $current, FieldOpsService $service)
    {
        $data = $request->validate([
            'bored_pile_id' => ['required', 'integer'],
            'number' => ['required', 'max:80'],
            'test_type' => ['required', Rule::in(PileTest::TYPES)],
            'provider_name' => ['nullable', 'max:150'],
            'scheduled_date' => ['required', 'date'],
            'method' => ['nullable', 'max:100'],
            'acceptance_criteria' => ['nullable', 'max:200'],
        ]);
        $pile = BoredPile::whereHas('project', fn ($q) => $q->where('company_id', $current->id()))->findOrFail($data['bored_pile_id']);
        $service->schedulePileTest($pile, $data, $request->user());

        return back()->with('status', 'Jadwal pengujian dibuat.');
    }

    public function recordTestResult(Request $request, PileTest $test, CurrentCompany $current, FieldOpsService $service)
    {
        abort_unless($test->company_id === $current->id(), 404);
        $data = $request->validate([
            'result_status' => ['required', 'in:passed,failed'],
            'interpretation' => ['nullable', 'max:2000'],
            'report_number' => ['nullable', 'max:80'],
            'ncr_number' => ['nullable', 'max:80'],
        ]);
        $service->recordPileTestResult($test, $data, $request->user());

        return back()->with('status', 'Hasil pengujian direkam.');
    }

    public function approveTest(Request $request, PileTest $test, CurrentCompany $current, FieldOpsService $service)
    {
        abort_unless($test->company_id === $current->id(), 404);
        $service->approvePileTestResult($test, $request->user());

        return back()->with('status', 'Hasil uji disetujui konsultan.');
    }

    public function uploadEvidence(Request $request, string $type, CurrentCompany $current, FieldOpsService $service)
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        abort_unless(in_array($type, array_keys(FieldEvidence::TYPES), true), 404);
        $evidence = $service->storeEvidence($type, (int) $data['id'], $request->file('file'), $request->user());

        return back()->with('status', "Foto terlampir (#{$evidence->id}).");
    }

    public function downloadEvidence(Request $request, FieldEvidence $evidence)
    {
        abort_unless($evidence->company_id === app(CurrentCompany::class)->id(), 404);
        abort_unless(Storage::disk('local')->exists($evidence->disk_path), 404);

        return Storage::disk('local')->download($evidence->disk_path, $evidence->original_name);
    }

    private function parseLayers(string $raw): array
    {
        $layers = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($raw)) ?: [] as $index => $line) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 3 || $parts[0] === '' || $parts[1] === '' || $parts[2] === '') {
                throw ValidationException::withMessages(['layers' => 'Baris '.($index + 1).' harus format: dari|ke|deskripsi.']);
            }
            $layers[] = ['depth_from_m' => $parts[0], 'depth_to_m' => $parts[1], 'soil_description' => $parts[2]];
        }
        throw_if($layers === [], ValidationException::withMessages(['layers' => 'Minimal satu lapisan tanah.']));

        return $layers;
    }
}
