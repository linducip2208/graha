<?php

namespace App\Http\Controllers;

use App\Models\BoredPile;
use App\Models\Project;
use App\Models\StoredFile;
use App\Services\Storage\DirectUploadService;
use App\Services\Storage\StorageRetentionService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

/**
 * Storage operations hub (ADR-078): dashboard agregat dari metadata DB
 * (tanpa bucket scan), aksi retensi ber-permission, dan direct upload
 * presigned dengan fallback server.
 */
class StorageController extends Controller
{
    public function dashboard(Request $request, CurrentCompany $current)
    {
        $companyId = $current->id();
        $projectId = $request->query('project');
        $base = StoredFile::where('company_id', $companyId)->whereNull('original_file_id');

        $totals = (clone $base)->selectRaw('COUNT(*) as objects, COALESCE(SUM(size_bytes), 0) as bytes')->first();
        $byCategory = (clone $base)->groupBy('category')->selectRaw('category, COUNT(*) as objects, COALESCE(SUM(size_bytes), 0) as bytes')->get();
        $byStatus = (clone $base)->groupBy('status')->selectRaw('status, COUNT(*) as objects')->get();
        $byDisk = (clone $base)->groupBy('disk')->selectRaw('disk, COUNT(*) as objects, COALESCE(SUM(size_bytes), 0) as bytes')->get();
        $bySubCategory = (clone $base)->where('category', 'photo')->whereNotNull('sub_category')
            ->groupBy('sub_category')->selectRaw('sub_category, COUNT(*) as objects')->orderByDesc('objects')->limit(14)->get();

        $projectFilter = null;
        $byProject = collect();
        if ($projectId !== null) {
            $projectFilter = Project::where('company_id', $companyId)->find((int) $projectId);
            if ($projectFilter !== null) {
                $byCategory = (clone $base)->where('project_id', $projectFilter->id)->groupBy('category')
                    ->selectRaw('category, COUNT(*) as objects, COALESCE(SUM(size_bytes), 0) as bytes')->get();
                $byStatus = (clone $base)->where('project_id', $projectFilter->id)->groupBy('status')
                    ->selectRaw('status, COUNT(*) as objects')->get();
            }
        } else {
            $byProject = (clone $base)->whereNotNull('project_id')->groupBy('project_id')
                ->selectRaw('project_id, COUNT(*) as objects, COALESCE(SUM(size_bytes), 0) as bytes')
                ->orderByDesc('bytes')->limit(20)->get();
        }

        return view('storage.dashboard', [
            'projects' => Project::where('company_id', $companyId)->orderBy('code')->get(['id', 'code', 'name']),
            'projectFilter' => $projectFilter,
            'totals' => $totals,
            'byCategory' => $byCategory,
            'byStatus' => $byStatus,
            'byDisk' => $byDisk,
            'bySubCategory' => $bySubCategory,
            'byProject' => $byProject,
        ]);
    }

    /** Aksi retensi — permission tinggi; semua terekam audit. */
    public function retentionAction(Request $request, StoredFile $file, CurrentCompany $current, StorageRetentionService $retention)
    {
        abort_unless($file->company_id === $current->id(), 404);
        abort_unless($request->user()->hasPermission('storage.manage', $current->id()), 403);
        $action = $request->validate(['action' => ['required', 'in:archive,restore,pending_delete,delete']])['action'];

        match ($action) {
            'archive' => $retention->archive($file, $request->user()),
            'restore' => $retention->restore($file, $request->user()),
            'pending_delete' => $retention->markPendingDelete($file, $request->user()),
            'delete' => $retention->physicalDelete($file, $request->user()),
        };

        return back()->with('status', "Aksi retensi '{$action}' dieksekusi dan teraudit.");
    }

    /** Minta direct upload: balas presigned URL bila didukung, else mode server. */
    public function requestUpload(Request $request, CurrentCompany $current, DirectUploadService $direct)
    {
        abort_unless($request->user()->hasPermission('project.manage', $current->id()), 403);
        $data = $request->validate([
            'bored_pile_id' => ['nullable', 'integer'],
            'category' => ['required', 'string', 'max:60'],
            'filename' => ['required', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:20971520'],
        ]);
        $pile = isset($data['bored_pile_id'])
            ? BoredPile::whereHas('project', fn ($q) => $q->where('company_id', $current->id()))->findOrFail($data['bored_pile_id'])
            : null;
        $payload = $direct->requestUpload($pile, $current->id(), $data['category'], $data['filename'], (int) $data['size'], $request->user());

        return response()->json($payload);
    }

    /** Finalize idempotent via client upload_id. */
    public function finalizeUpload(Request $request, CurrentCompany $current, DirectUploadService $direct)
    {
        abort_unless($request->user()->hasPermission('project.manage', $current->id()), 403);
        $data = $request->validate([
            'upload_id' => ['required', 'uuid'],
            'sha256' => ['nullable', 'regex:/^[a-f0-9]{64}$/i'],
            'size' => ['nullable', 'integer', 'min:1'],
        ]);
        $file = $direct->finalize($data['upload_id'], $current->id(), $data['sha256'] ?? null, $data['size'] ?? null);

        return response()->json(['status' => 'ready', 'uuid' => $file->uuid, 'sha256' => substr($file->sha256, 0, 16).'…']);
    }
}
