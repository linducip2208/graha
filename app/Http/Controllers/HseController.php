<?php

namespace App\Http\Controllers;

use App\Models\ApprovalWorkflow;
use App\Models\HseIncident;
use App\Models\HseIncidentAction;
use App\Models\JobSafetyAnalysis;
use App\Models\ManagementReview;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkPermit;
use App\Services\ApprovalEngine;
use App\Services\HseService;
use App\Services\ManagementReviewService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class HseController extends Controller
{
    public function index(CurrentCompany $current)
    {
        $id = $current->id();

        return view('hse.index', ['projects' => Project::where('company_id', $id)->whereIn('status', ['active', 'in_progress'])->get(), 'users' => User::whereHas('companies', fn ($query) => $query->whereKey($id))->get(), 'jsas' => JobSafetyAnalysis::where('company_id', $id)->latest()->get(), 'permits' => WorkPermit::where('company_id', $id)->latest()->get(), 'incidents' => HseIncident::where('company_id', $id)->with('actions')->latest('occurred_at')->get(), 'reviews' => ManagementReview::where('company_id', $id)->latest('meeting_date')->get(), 'workflows' => ApprovalWorkflow::where('company_id', $id)->where('document_type', 'jsa')->where('is_active', true)->get()]);
    }

    public function jsa(Request $request, CurrentCompany $current)
    {
        $data = $request->validate(['project_id' => ['required', 'exists:projects,id'], 'number' => ['required', 'max:80'], 'activity' => ['required', 'max:255'], 'location' => ['nullable', 'max:255'], 'hazards' => ['required'], 'controls' => ['required'], 'risk_level' => ['required', 'in:low,medium,high,extreme'], 'valid_from' => ['required', 'date'], 'valid_until' => ['required', 'date', 'after_or_equal:valid_from']]);
        abort_unless(Project::where('company_id', $current->id())->whereKey($data['project_id'])->exists(), 422);
        JobSafetyAnalysis::create([...$data, 'company_id' => $current->id(), 'hazards' => array_values(array_filter(array_map('trim', explode("\n", $data['hazards'])))), 'controls' => array_values(array_filter(array_map('trim', explode("\n", $data['controls'])))), 'prepared_by' => $request->user()->id]);

        return back()->with('status', 'Draft JSA dibuat.');
    }

    public function submitJsa(Request $request, JobSafetyAnalysis $jsa, CurrentCompany $current, ApprovalEngine $engine)
    {
        $this->owned($jsa, $current);
        $data = $request->validate(['workflow_id' => ['required', 'exists:approval_workflows,id']]);
        $workflow = ApprovalWorkflow::where('company_id', $current->id())->where('document_type', 'jsa')->findOrFail($data['workflow_id']);
        $engine->submit($workflow, $jsa, $request->user(), 'jsa-submit-'.$jsa->id);
        $jsa->update(['status' => 'pending_approval']);

        return back()->with('status', 'JSA dikirim ke approval.');
    }

    public function activateJsa(Request $request, JobSafetyAnalysis $jsa, CurrentCompany $current, HseService $service)
    {
        $this->owned($jsa, $current);
        $service->activateApprovedJsa($jsa, $request->user());

        return back()->with('status', 'JSA approved dan aktif.');
    }

    public function permit(Request $request, JobSafetyAnalysis $jsa, CurrentCompany $current, HseService $service)
    {
        $this->owned($jsa, $current);
        $data = $request->validate(['number' => ['required', 'max:80'], 'permit_type' => ['required', 'max:50'], 'work_location' => ['required', 'max:255'], 'valid_from' => ['required', 'date'], 'valid_until' => ['required', 'date', 'after:valid_from']]);
        $service->issuePermit($jsa, $data, $request->user());

        return back()->with('status', 'Permit to Work diterbitkan.');
    }

    public function incident(Request $request, CurrentCompany $current)
    {
        $data = $request->validate(['project_id' => ['required', 'exists:projects,id'], 'number' => ['required', 'max:80'], 'type' => ['required', 'in:near_miss,incident,environmental,unsafe_condition'], 'severity' => ['required', 'in:low,medium,high,fatal'], 'occurred_at' => ['required', 'date'], 'location' => ['required'], 'description' => ['required'], 'immediate_action' => ['nullable'], 'root_cause' => ['nullable']]);
        abort_unless(Project::where('company_id', $current->id())->whereKey($data['project_id'])->exists(), 422);
        HseIncident::create([...$data, 'company_id' => $current->id(), 'reported_by' => $request->user()->id]);

        return back()->with('status', 'Incident/near miss dilaporkan.');
    }

    public function action(Request $request, HseIncident $incident, CurrentCompany $current)
    {
        $this->owned($incident, $current);
        $data = $request->validate(['action' => ['required'], 'owner_id' => ['required', 'exists:users,id'], 'due_at' => ['required', 'date'], 'evidence' => ['nullable']]);
        abort_unless(User::whereKey($data['owner_id'])->whereHas('companies', fn ($query) => $query->whereKey($current->id()))->exists(), 422);
        $incident->actions()->create($data);

        return back()->with('status', 'Tindakan incident ditambahkan.');
    }

    public function verify(Request $request, HseIncidentAction $action, CurrentCompany $current, HseService $service)
    {
        abort_unless($action->hseIncident()->where('company_id', $current->id())->exists(), 404);
        if ($request->filled('evidence')) {
            $action->update(['evidence' => $request->input('evidence')]);
        } $service->verifyAction($action, $request->user());

        return back()->with('status', 'Tindakan diverifikasi efektif.');
    }

    public function close(Request $request, HseIncident $incident, CurrentCompany $current, HseService $service)
    {
        $this->owned($incident, $current);
        $service->closeIncident($incident, $request->user());

        return back()->with('status', 'Incident ditutup.');
    }

    public function review(Request $request, CurrentCompany $current, ManagementReviewService $service)
    {
        $data = $request->validate(['number' => ['required', 'max:80'], 'meeting_date' => ['required', 'date']]);
        $service->createSnapshot($current->id(), $data['number'], $data['meeting_date'], $request->user());

        return back()->with('status', 'Management review snapshot dibuat.');
    }

    private function owned($model, CurrentCompany $current): void
    {
        abort_unless($model->company_id === $current->id(), 404);
    }
}
