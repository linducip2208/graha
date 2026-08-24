<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\HandoverPackageService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProjectHandoverController extends Controller
{
    /** Bangun handover package (Phase 33): ZIP as-built + dossier + manifest. */
    public function build(Request $request, Project $project, CurrentCompany $current, HandoverPackageService $service)
    {
        abort_unless($project->company_id === $current->id(), 404);
        abort_unless($request->user()->hasPermission('project.manage', $current->id()), 403);
        $data = $request->validate(['pile_ids' => ['nullable', 'array'], 'pile_ids.*' => ['integer']]);
        try {
            $result = $service->build($project, $data['pile_ids'] ?? null, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', "Handover package {$result['stored']->original_name} siap ({$result['piles']->count()} pile) · unduh di menu dokumen passport.");
    }
}
