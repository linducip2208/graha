<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectCostingService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class ProjectCostingController extends Controller
{
    public function index(CurrentCompany $current, ProjectCostingService $service)
    {
        $projects = Project::where('company_id', $current->id())->orderBy('code')->get();

        return view('project-costing.index', ['projects' => $projects, 'summaries' => collect($service->summariesFor($projects))]);
    }

    public function forecast(Request $request, CurrentCompany $current, ProjectCostingService $service)
    {
        $data = $request->validate(['project_id' => ['required', 'integer'], 'forecast_date' => ['required', 'date'], 'cost_to_complete' => ['required', 'decimal:0,2', 'min:0'], 'basis' => ['nullable', 'max:2000'], 'idempotency_key' => ['required', 'max:120']]);
        $project = Project::where('company_id', $current->id())->findOrFail($data['project_id']);
        $service->forecast($project, $data, $request->user());

        return back()->with('status', 'Forecast biaya proyek disimpan.');
    }
}
