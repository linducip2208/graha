<?php

namespace App\Http\Controllers;

use App\Models\BoredPile;
use App\Models\BoredPileDrilling;
use App\Models\ConcreteDelivery;
use App\Models\FieldEvidence;
use App\Models\PileGeometryReading;
use App\Models\PileTest;
use App\Models\PileTremieLog;
use App\Models\Project;
use App\Models\SlurryTest;
use App\Models\Vendor;
use App\Services\FieldOpsService;
use App\Services\PourCurveService;
use App\Services\SlurryControlService;
use App\Services\TremieLogService;
use App\Support\AccessScopeService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FieldOpsController extends Controller
{
    public function index(Request $request, CurrentCompany $current, AccessScopeService $scope)
    {
        $companyId = $current->id();
        $projects = $scope->applyToProjectQuery(Project::query(), $request->user(), $companyId)->whereIn('status', ['active', 'in_progress'])->orderBy('code')->get();
        $project = $projects->firstWhere('id', (int) $request->query('project')) ?? $projects->first();
        $piles = collect();
        $drillings = collect();
        $deliveries = collect();
        $tests = collect();
        $slurryTests = collect();
        $tremieLogs = collect();
        if ($project) {
            $piles = BoredPile::where('project_id', $project->id)->orderBy('pile_number')->get();
            $drillings = BoredPileDrilling::whereIn('bored_pile_id', $piles->pluck('id'))->with(['pile', 'layers', 'recorder', 'verifier'])->latest()->limit(50)->get();
            $deliveries = ConcreteDelivery::where('project_id', $project->id)->with(['pile', 'vendor'])->orderBy('arrived_at')->limit(50)->get();
            $tests = PileTest::where('project_id', $project->id)->with('pile')->latest('scheduled_date')->limit(50)->get();
            $slurryTests = SlurryTest::whereIn('bored_pile_id', $piles->pluck('id'))->with(['pile', 'verifier'])->latest('tested_at')->limit(50)->get();
            $tremieLogs = PileTremieLog::whereIn('bored_pile_id', $piles->pluck('id'))->with('pile')->latest('recorded_at')->limit(50)->get();
        }

        return view('projects.field-ops', [
            'projects' => $projects,
            'project' => $project,
            'piles' => $piles,
            'drillings' => $drillings,
            'deliveries' => $deliveries,
            'tests' => $tests,
            'slurryTests' => $slurryTests,
            'tremieLogs' => $tremieLogs,
            'vendors' => Vendor::where('company_id', $companyId)->orderBy('name')->get(),
            'testTypes' => PileTest::TYPES,
            'slurryPolicyEnabled' => app(SlurryControlService::class)->policyEnabled($companyId),
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
        $pile = $this->findAuthorizedPile($data['bored_pile_id'], $request, $current);
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
        $pile = $this->findAuthorizedPile($data['bored_pile_id'], $request, $current);
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

    public function storeSlurry(Request $request, CurrentCompany $current, SlurryControlService $service)
    {
        $data = $request->validate([
            'bored_pile_id' => ['required', 'integer'],
            'phase' => ['required', Rule::in(SlurryTest::PHASES)],
            'type' => ['required', Rule::in(SlurryTest::TYPES)],
            'tested_at' => ['required', 'date'],
            'batch_number' => ['nullable', 'max:60'],
            'density' => ['nullable', 'decimal:0,3'],
            'viscosity' => ['nullable', 'decimal:0,2'],
            'ph' => ['nullable', 'decimal:0,2', 'between:0,14'],
            'sand_content_percent' => ['nullable', 'decimal:0,2', 'between:0,100'],
            'temperature' => ['nullable', 'decimal:0,2'],
            'notes' => ['nullable', 'max:2000'],
        ]);
        $pile = $this->findAuthorizedPile($data['bored_pile_id'], $request, $current);
        unset($data['bored_pile_id']);
        $test = $service->record($pile, $data, $request->user());
        $violations = count($service->violations($test));

        return back()->with('status', $service->policyEnabled($current->id())
            ? "Uji slurry terekam — {$violations} pelanggaran limit terdeteksi (keputusan oleh QA)."
            : 'Uji slurry terekam (record only — kebijakan limit tidak aktif).');
    }

    public function decideSlurry(Request $request, SlurryTest $slurryTest, CurrentCompany $current, SlurryControlService $service)
    {
        abort_unless($slurryTest->company_id === $current->id(), 404);
        abort_unless($request->user()->hasPermission('qms.verify', $current->id()), 403);
        $decision = $request->validate(['decision' => ['required', 'in:accepted,rejected']])['decision'];
        $service->decide($slurryTest, $decision, $request->user());

        return back()->with('status', "Uji slurry {$decision} oleh QA.");
    }

    public function storeTremie(Request $request, CurrentCompany $current, TremieLogService $service)
    {
        $data = $request->validate([
            'bored_pile_id' => ['required', 'integer'],
            'recorded_at' => ['required', 'date'],
            'tremie_total_length_m' => ['required', 'decimal:0,2', 'gt:0'],
            'tremie_tip_depth_m' => ['required', 'decimal:0,2', 'min:0'],
            'concrete_level_m' => ['nullable', 'decimal:0,2', 'min:0'],
            'notes' => ['nullable', 'max:2000'],
        ]);
        $pile = $this->findAuthorizedPile($data['bored_pile_id'], $request, $current);
        unset($data['bored_pile_id']);
        $log = $service->record($pile, $data, $request->user());
        $message = match ($log->flag) {
            'out_of_range' => 'Log tremie terekam — EMBEDMENT DI LUAR RENTANG (indikator; keputusan tetap engineer).',
            'warning' => 'Log tremie terekam — embedment mendekati batas atas (warning).',
            default => 'Log tremie terekam.',
        };

        return back()->with('status', $message);
    }

    public function storePourInterval(Request $request, CurrentCompany $current, PourCurveService $service)
    {
        $data = $request->validate([
            'bored_pile_id' => ['required', 'integer'],
            'recorded_at' => ['required', 'date'],
            'depth_or_level_m' => ['required', 'decimal:0,3', 'min:0'],
            'incremental_volume_m3' => ['required', 'decimal:0,4', 'min:0'],
            'concrete_delivery_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'max:1000'],
        ]);
        $pile = $this->findAuthorizedPile($data['bored_pile_id'], $request, $current);
        unset($data['bored_pile_id']);
        $service->recordInterval($pile, $data, $request->user());

        return back()->with('status', 'Interval pour terekam — kurva aktual vs teoretis diperbarui.');
    }

    public function importGeometry(Request $request, CurrentCompany $current, PourCurveService $service)
    {
        $data = $request->validate([
            'bored_pile_id' => ['required', 'integer'],
            'source' => ['required', Rule::in(PileGeometryReading::SOURCES)],
            'csv' => ['required', 'string', 'max:200000'],
        ]);
        $pile = $this->findAuthorizedPile($data['bored_pile_id'], $request, $current);
        try {
            $count = $service->importGeometryCsv($pile, $data['csv'], $data['source'], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', "{$count} baris geometri lubang terimpor (sumber: {$data['source']}).");
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
        $pile = $this->findAuthorizedPile($data['bored_pile_id'], $request, $current);
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

    /** Tipe evidence milik Field Ops; cage/casing/tool punya route + permission sendiri. */
    private const FIELD_OPS_TYPES = ['drilling', 'delivery', 'test'];

    public function uploadEvidence(Request $request, string $type, CurrentCompany $current, FieldOpsService $service)
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        abort_unless(in_array($type, self::FIELD_OPS_TYPES, true), 404);
        $evidence = $service->storeEvidence($type, (int) $data['id'], $request->file('file'), $request->user());

        return back()->with('status', "Foto terlampir (#{$evidence->id}).");
    }

    public function downloadEvidence(Request $request, FieldEvidence $evidence)
    {
        return $this->serveEvidence($evidence, true);
    }

    /** Pratinjau inline untuk tag <img> — tetap ber-authorization per company. */
    public function fileEvidence(Request $request, FieldEvidence $evidence)
    {
        return $this->serveEvidence($evidence, false);
    }

    private function serveEvidence(FieldEvidence $evidence, bool $asAttachment)
    {
        abort_unless($evidence->company_id === app(CurrentCompany::class)->id(), 404);
        $diskName = filled($evidence->disk) ? $evidence->disk : 'local';
        abort_unless(array_key_exists($diskName, config('filesystems.disks', [])), 404);
        $disk = Storage::disk($diskName);
        abort_unless($disk->exists($evidence->disk_path), 404);

        if ($diskName === 'local') {
            return $asAttachment
                ? $disk->download($evidence->disk_path, $evidence->original_name)
                : $disk->response($evidence->disk_path, $evidence->mime);
        }

        // Object storage S3-compatible: redirect ke temporary URL berbatas waktu.
        return redirect()->away($disk->temporaryUrl($evidence->disk_path, now()->addMinutes(15), [
            'ResponseContentDisposition' => ($asAttachment ? 'attachment' : 'inline').'; filename="'.addcslashes($evidence->original_name, '"\\').'"',
        ]));
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

    private function findAuthorizedPile(int|string $pileId, Request $request, CurrentCompany $current): BoredPile
    {
        $pile = BoredPile::with('project')->findOrFail((int) $pileId);
        abort_unless($pile->project?->company_id === $current->id(), 404);
        abort_unless(app(AccessScopeService::class)->canAccessProject($request->user(), $pile->project), 404);

        return $pile;
    }
}
