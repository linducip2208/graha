<?php

namespace App\Http\Controllers;

use App\Models\ApprovalWorkflow;
use App\Models\HseExposureLog;
use App\Models\HseIncident;
use App\Models\HseIncidentAction;
use App\Models\JobSafetyAnalysis;
use App\Models\ManagementReview;
use App\Models\NumberSequence;
use App\Models\PpeIssuance;
use App\Models\Project;
use App\Models\SafetyObservation;
use App\Models\User;
use App\Models\WorkPermit;
use App\Services\ApprovalEngine;
use App\Services\AuditTrail;
use App\Services\HseMetricsService;
use App\Services\HseService;
use App\Services\ManagementReviewService;
use App\Services\NumberSequenceService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HseController extends Controller
{
    public function index(CurrentCompany $current)
    {
        $id = $current->id();

        return view('hse.index', ['projects' => Project::where('company_id', $id)->whereIn('status', ['active', 'in_progress'])->get(), 'users' => User::whereHas('companies', fn ($query) => $query->whereKey($id))->get(), 'jsas' => JobSafetyAnalysis::where('company_id', $id)->latest()->get(), 'permits' => WorkPermit::where('company_id', $id)->latest()->get(), 'incidents' => HseIncident::where('company_id', $id)->with('actions')->latest('occurred_at')->get(), 'reviews' => ManagementReview::where('company_id', $id)->latest('meeting_date')->get(), 'workflows' => ApprovalWorkflow::where('company_id', $id)->where('document_type', 'jsa')->where('is_active', true)->get(), 'observations' => SafetyObservation::where('company_id', $id)->with('project')->latest('observed_at')->limit(100)->get(), 'ppeIssuances' => PpeIssuance::where('company_id', $id)->with('person')->latest('issued_at')->limit(100)->get()]);
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

    public function storeObservation(Request $request, CurrentCompany $current, AuditTrail $audit)
    {
        $data = $request->validate(['project_id' => ['nullable', 'integer'], 'category' => ['required', 'in:unsafe_act,unsafe_condition,near_miss'], 'observed_at' => ['required', 'date'], 'location' => ['required', 'max:255'], 'description' => ['required', 'max:2000'], 'immediate_action' => ['nullable', 'max:2000']]);
        if (! empty($data['project_id'])) {
            abort_unless(Project::where('company_id', $current->id())->whereKey($data['project_id'])->exists(), 422);
        } else {
            unset($data['project_id']);
        }
        NumberSequence::firstOrCreate(['company_id' => $current->id(), 'document_type' => 'safety_observation'], ['prefix' => 'OBS', 'padding' => 4, 'last_reset_year' => now()->year]);
        $observation = SafetyObservation::create([...$data, 'company_id' => $current->id(), 'number' => app(NumberSequenceService::class)->next($current->id(), 'safety_observation'), 'reported_by' => $request->user()->id]);
        $audit->record($current->id(), $request->user()->id, 'hse.observation_reported', $observation);

        return back()->with('status', 'Observasi keselamatan dicatat.');
    }

    public function resolveObservation(Request $request, SafetyObservation $observation, CurrentCompany $current, AuditTrail $audit)
    {
        abort_unless($observation->company_id === $current->id(), 404);
        throw_unless($observation->status === 'open', ValidationException::withMessages(['status' => 'Observasi sudah ditutup.']));
        $data = $request->validate(['resolution_notes' => ['required', 'max:2000']]);
        $observation->update(['status' => 'resolved', 'resolution_notes' => $data['resolution_notes'], 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
        $audit->record($current->id(), $request->user()->id, 'hse.observation_resolved', $observation);

        return back()->with('status', 'Observasi diselesaikan.');
    }

    public function issuePpe(Request $request, CurrentCompany $current)
    {
        $data = $request->validate(['user_id' => ['required', 'integer'], 'item_name' => ['required', 'max:150'], 'size' => ['nullable', 'max:30'], 'quantity' => ['required', 'integer', 'min:1', 'max:999'], 'issued_at' => ['required', 'date'], 'condition_out' => ['required', 'in:good,worn,damaged']]);
        abort_unless(User::whereHas('companies', fn ($q) => $q->whereKey($current->id()))->whereKey($data['user_id'])->exists(), 422);
        PpeIssuance::create([...$data, 'company_id' => $current->id(), 'issued_by' => $request->user()->id]);

        return back()->with('status', 'PPE diterbitkan.');
    }

    public function returnPpe(Request $request, PpeIssuance $issuance, CurrentCompany $current)
    {
        abort_unless($issuance->company_id === $current->id(), 404);
        $data = $request->validate(['returned_at' => ['required', 'date'], 'condition_in' => ['required', 'in:good,worn,damaged']]);
        $issuance->update(['returned_at' => $data['returned_at'], 'condition_in' => $data['condition_in']]);

        return back()->with('status', 'Pengembalian PPE dicatat.');
    }

    public function storeExposure(Request $request, CurrentCompany $current)
    {
        $data = $request->validate(['period_month' => ['required', 'date'], 'man_hours' => ['required', 'decimal:0,2', 'gt:0'], 'avg_headcount' => ['nullable', 'integer', 'min:0'], 'notes' => ['nullable', 'max:1000']]);
        HseExposureLog::updateOrCreate(['company_id' => $current->id(), 'period_month' => date('Y-m-01', strtotime($data['period_month']))], ['man_hours' => $data['man_hours'], 'avg_headcount' => $data['avg_headcount'] ?? null, 'notes' => $data['notes'] ?? null, 'created_by' => $request->user()->id]);

        return back()->with('status', 'Jam kerja periode tersimpan. KPI FR/SR dihitung dari data ini.');
    }

    public function metrics(CurrentCompany $current, HseMetricsService $metrics)
    {
        [$from, $to] = $this->metricRange();
        try {
            $summary = $metrics->summary($current->id(), $from, $to);
            $error = null;
        } catch (\InvalidArgumentException $e) {
            $summary = null;
            $error = $e->getMessage();
        }
        $incidents = HseIncident::where('company_id', $current->id())->latest('occurred_at')->limit(50)->get();

        return view('hse.metrics', ['from' => $from, 'to' => $to, 'summary' => $summary, 'error' => $error, 'incidents' => $incidents]);
    }

    private function metricRange(): array
    {
        return [now()->startOfYear()->toDateString(), now()->endOfDay()->toDateString()];
    }

    private function owned($model, CurrentCompany $current): void
    {
        abort_unless($model->company_id === $current->id(), 404);
    }
}
