<?php

namespace App\Http\Controllers;

use App\Models\FieldEvidence;
use App\Models\Project;
use App\Models\Tool;
use App\Models\User;
use App\Services\FieldOpsService;
use App\Services\ToolService;
use App\Support\Tenancy\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ToolController extends Controller
{
    public function index(CurrentCompany $current)
    {
        $companyId = $current->id();

        return view('inventory.tools', [
            'tools' => Tool::where('company_id', $companyId)->with(['holder', 'movements' => fn ($q) => $q->limit(4), 'movements.holder'])->orderBy('code')->get(),
            'members' => User::whereIn('id', DB::table('company_user')->where('company_id', $companyId)->where('is_active', true)->select('user_id'))->orderBy('name')->get(),
            'projects' => Project::where('company_id', $companyId)->whereIn('status', ['active', 'in_progress'])->orderBy('code')->get(),
            'evidences' => FieldEvidence::where('company_id', $companyId)->where('evidence_type', 'tool')
                ->whereIn('evidence_id', Tool::where('company_id', $companyId)->select('id'))
                ->with('uploader')->latest()->get()->groupBy(fn ($e) => $e->evidence_id),
        ]);
    }

    public function store(Request $request, CurrentCompany $current)
    {
        $data = $request->validate([
            'code' => ['required', 'max:40'],
            'name' => ['required', 'max:150'],
            'category' => ['nullable', 'max:60'],
            'purchase_cost' => ['nullable', 'decimal:0,2', 'min:0'],
            'notes' => ['nullable', 'max:500'],
        ]);
        abort_unless(Tool::where('company_id', $current->id())->where('code', $data['code'])->doesntExist(), 422);
        Tool::create([...$data, 'company_id' => $current->id()]);

        return back()->with('status', 'Alat terdaftar.');
    }

    public function checkOut(Request $request, Tool $tool, CurrentCompany $current, ToolService $service)
    {
        abort_unless($tool->company_id === $current->id(), 404);
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'expected_return_at' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'max:500'],
        ]);
        abort_unless(User::whereKey($data['user_id'])->exists(), 422);
        $service->checkOut($tool->refresh(), User::find($data['user_id']), $data['project_id'] ?? null, isset($data['expected_return_at']) ? Carbon::parse($data['expected_return_at']) : null, $data['notes'] ?? '', $request->user());

        return back()->with('status', 'Alat dicatat keluar.');
    }

    public function checkIn(Request $request, Tool $tool, CurrentCompany $current, ToolService $service)
    {
        abort_unless($tool->company_id === $current->id(), 404);
        $data = $request->validate(['condition_note' => ['nullable', 'max:500']]);
        $service->checkIn($tool->refresh(), $data['condition_note'] ?? '', $request->user());

        return back()->with('status', 'Alat dikembalikan ke gudang.');
    }

    public function markLost(Request $request, Tool $tool, CurrentCompany $current, ToolService $service)
    {
        abort_unless($tool->company_id === $current->id(), 404);
        $data = $request->validate(['lost_note' => ['required', 'max:500']]);
        $service->markLost($tool->refresh(), $data['lost_note'], $request->user());

        return back()->with('status', 'Alat dilaporkan hilang.');
    }

    /** Foto evidence alat (kondisi saat keluar/masuk, kerusakan, kehilangan). */
    public function uploadEvidence(Request $request, Tool $tool, CurrentCompany $current, FieldOpsService $service)
    {
        abort_unless($tool->company_id === $current->id(), 404);
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $evidence = $service->storeEvidence('tool', $tool->id, $data['file'], $request->user());

        return back()->with('status', "Foto alat terlampir (#{$evidence->id}).");
    }
}
