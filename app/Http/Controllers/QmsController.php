<?php

namespace App\Http\Controllers;

use App\Models\BoredPile;
use App\Models\CorrectiveAction;
use App\Models\Customer;
use App\Models\CustomerSatisfactionSurvey;
use App\Models\Department;
use App\Models\InspectionTestPlan;
use App\Models\InternalAudit;
use App\Models\ItpItem;
use App\Models\Nonconformity;
use App\Models\Project;
use App\Models\QualityObjective;
use App\Models\RiskOpportunity;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\ItpService;
use App\Services\QmsService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QmsController extends Controller
{
    /** Record workspace NCR: header + akar masalah + CAPA + verifikasi. */
    public function showNcr(Request $request, Nonconformity $ncr, CurrentCompany $current)
    {
        abort_unless($ncr->company_id === $current->id(), 404);
        $ncr->load(['actions.owner', 'actions.verifier', 'project:id,code,name']);

        return view('qms.ncr-show', ['ncr' => $ncr]);
    }

    public function index(Request $request, CurrentCompany $current)
    {
        $companyId = $current->id();
        $ncrs = Nonconformity::where('company_id', $companyId)->with('actions')->latest()->limit(50)->get();

        return view('qms.index', [
            'objectives' => QualityObjective::where('company_id', $companyId)->orderBy('due_date')->limit(20)->get(),
            'surveys' => CustomerSatisfactionSurvey::where('company_id', $companyId)->with(['project', 'customer'])->latest('survey_date')->limit(15)->get(),
            'surveyAvg' => (string) CustomerSatisfactionSurvey::where('company_id', $companyId)->selectRaw('AVG((quality_score + schedule_score + communication_score) / 3.0) as avg')->value('avg'),
            'projects' => Project::where('company_id', $companyId)->orderBy('code')->get(),
            'customers' => Customer::where('company_id', $companyId)->orderBy('name')->get(),
            'risks' => RiskOpportunity::where('company_id', $companyId)->latest()->limit(50)->get(),
            'ncrs' => $ncrs,
            'audits' => InternalAudit::where('company_id', $companyId)->latest('scheduled_at')->limit(50)->get(),
            'departments' => Department::where('company_id', $companyId)->orderBy('name')->get(),
            'users' => User::whereHas('companies', fn ($query) => $query->whereKey($companyId))->orderBy('name')->get(),
            'kanban' => $request->query('view') === 'kanban' ? $this->ncrKanban($ncrs) : null,
        ]);
    }

    /** Papan kanban NCR per status, dibangun dari koleksi yang sudah dimuat. */
    private function ncrKanban($ncrs): array
    {
        $columns = [];
        foreach (['open' => 'Terbuka', 'closed' => 'Selesai'] as $status => $label) {
            $columns[] = ['label' => $label, 'items' => $ncrs->where('status', $status)->map(fn ($ncr) => [
                'title' => $ncr->number,
                'subtitle' => str($ncr->description)->limit(60),
                'meta' => strtoupper($ncr->severity),
                'href' => '/admin/qms#timeline-ncr',
            ])->values()];
        }

        return $columns;
    }

    public function risk(Request $request, CurrentCompany $current, QmsService $service)
    {
        $data = $request->validate([
            'code' => ['required', 'max:50', 'unique:risk_opportunities,code,NULL,id,company_id,'.$current->id()],
            'type' => ['required', 'in:risk,opportunity'], 'title' => ['required', 'max:255'],
            'description' => ['required'], 'likelihood' => ['required', 'integer', 'between:1,5'],
            'impact' => ['required', 'integer', 'between:1,5'], 'controls' => ['nullable'],
            'owner_id' => ['required', 'exists:users,id'], 'review_at' => ['nullable', 'date'],
        ]);
        $this->ensureCompanyUser($data['owner_id'], $current->id());
        $service->createRisk([...$data, 'company_id' => $current->id()], $request->user());

        return back()->with('status', 'Risiko/peluang dicatat dan diberi skor.');
    }

    public function ncr(Request $request, CurrentCompany $current)
    {
        $data = $request->validate([
            'number' => ['required', 'max:80', 'unique:nonconformities,number,NULL,id,company_id,'.$current->id()],
            'source_type' => ['required', 'max:40'], 'severity' => ['required', 'in:major,minor,observation'],
            'description' => ['required'], 'containment' => ['nullable'], 'root_cause' => ['nullable'],
            'due_at' => ['nullable', 'date'],
        ]);
        Nonconformity::create([...$data, 'company_id' => $current->id(), 'reported_by' => $request->user()->id]);

        return back()->with('status', 'NCR dicatat.');
    }

    public function capa(Request $request, Nonconformity $ncr, CurrentCompany $current)
    {
        abort_unless($ncr->company_id === $current->id(), 404);
        $data = $request->validate(['action' => ['required'], 'owner_id' => ['required', 'exists:users,id'], 'due_at' => ['required', 'date'], 'evidence' => ['nullable']]);
        $this->ensureCompanyUser($data['owner_id'], $current->id());
        $ncr->actions()->create($data);

        return back()->with('status', 'Corrective action ditambahkan.');
    }

    public function verify(Request $request, CorrectiveAction $action, CurrentCompany $current, QmsService $service)
    {
        abort_unless($action->nonconformity()->where('company_id', $current->id())->exists(), 404);
        $data = $request->validate(['effectiveness_notes' => ['required'], 'evidence' => ['nullable']]);
        if (filled($data['evidence'] ?? null)) {
            $action->update(['evidence' => $data['evidence']]);
        }
        $service->verifyCapa($action, $request->user(), $data['effectiveness_notes']);

        return back()->with('status', 'Efektivitas CAPA diverifikasi secara independen.');
    }

    public function audit(Request $request, CurrentCompany $current, QmsService $service)
    {
        $data = $request->validate([
            'number' => ['required', 'max:80', 'unique:internal_audits,number,NULL,id,company_id,'.$current->id()],
            'scope' => ['required'], 'criteria' => ['required'], 'department_id' => ['required', 'exists:departments,id'],
            'auditor_id' => ['required', 'exists:users,id'], 'auditee_id' => ['required', 'different:auditor_id', 'exists:users,id'],
            'scheduled_at' => ['required', 'date'],
        ]);
        abort_unless(Department::where('company_id', $current->id())->whereKey($data['department_id'])->exists(), 422);
        $this->ensureCompanyUser($data['auditor_id'], $current->id());
        $this->ensureCompanyUser($data['auditee_id'], $current->id());
        $service->scheduleAudit([...$data, 'company_id' => $current->id()], $request->user());

        return back()->with('status', 'Audit internal dijadwalkan dengan pemeriksaan independensi.');
    }

    public function storeObjective(Request $request, CurrentCompany $current)
    {
        $data = $request->validate([
            'title' => ['required', 'max:200'], 'kpi_metric' => ['nullable', 'max:150'],
            'target_value' => ['nullable', 'decimal:0,2'], 'actual_value' => ['nullable', 'decimal:0,2'],
            'owner_id' => ['nullable', 'exists:users,id'], 'due_date' => ['nullable', 'date'],
        ]);
        if (! empty($data['owner_id'])) {
            $this->ensureCompanyUser((int) $data['owner_id'], $current->id());
        }
        QualityObjective::create([...$data, 'company_id' => $current->id()]);

        return back()->with('status', 'Sasaran mutu ditambahkan.');
    }

    public function updateObjectiveActual(Request $request, QualityObjective $objective, CurrentCompany $current)
    {
        abort_unless($objective->company_id === $current->id(), 404);
        $data = $request->validate(['actual_value' => ['required', 'decimal:0,2']]);
        $objective->update(['actual_value' => $data['actual_value']]);

        return back()->with('status', 'Realisasi sasaran mutu diperbarui.');
    }

    public function storeSurvey(Request $request, CurrentCompany $current)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'], 'project_id' => ['nullable', 'integer'],
            'respondent_name' => ['nullable', 'max:150'], 'survey_date' => ['required', 'date'],
            'quality_score' => ['required', 'integer', 'between:1,5'],
            'schedule_score' => ['required', 'integer', 'between:1,5'],
            'communication_score' => ['required', 'integer', 'between:1,5'],
            'comments' => ['nullable', 'max:1000'], 'follow_up_action' => ['nullable', 'max:300'],
        ]);
        abort_unless(Customer::where('company_id', $current->id())->whereKey($data['customer_id'])->exists(), 422);
        if (! empty($data['project_id'])) {
            abort_unless(Project::where('company_id', $current->id())->whereKey($data['project_id'])->exists(), 422);
        } else {
            unset($data['project_id']);
        }
        CustomerSatisfactionSurvey::create([...$data, 'company_id' => $current->id(), 'recorded_by' => $request->user()->id]);

        return back()->with('status', 'Survei kepuasan pelanggan tercatat.');
    }

    private function ensureCompanyUser(int $userId, int $companyId): void
    {
        abort_unless(User::whereKey($userId)->whereHas('companies', fn ($query) => $query->whereKey($companyId))->exists(), 422);
    }

    public function itps(CurrentCompany $current)
    {
        $plans = InspectionTestPlan::where('company_id', $current->id())->with(['project', 'boredPile', 'items.inspections'])->orderByDesc('created_at')->limit(100)->get();
        $openHolds = $plans->flatMap->items->filter(fn ($item) => $item->holdOpen())->count();

        return view('qms.itps', [
            'plans' => $plans,
            'projects' => Project::where('company_id', $current->id())->whereIn('status', ['active', 'in_progress'])->orderBy('code')->get(),
            'users' => User::whereHas('companies', fn ($q) => $q->whereKey($current->id()))->get(),
            'stats' => ['total' => $plans->count(), 'active' => $plans->where('status', 'active')->count(), 'hold_open' => $openHolds, 'inspections' => $plans->flatMap->items->flatMap->inspections->count()],
        ]);
    }

    public function storeItp(Request $request, CurrentCompany $current, ItpService $service)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'], 'bored_pile_id' => ['nullable', 'integer'],
            'title' => ['required', 'max:200'], 'notes' => ['nullable', 'max:2000'],
            'stage' => ['required', 'array', 'min:1'], 'stage.*' => ['required', 'max:150'],
            'method' => ['required', 'array'], 'method.*' => ['required', 'max:150'],
            'acceptance_criteria' => ['required', 'array'], 'acceptance_criteria.*' => ['required', 'max:1000'],
            'checkpoint_type' => ['required', 'array'], 'checkpoint_type.*' => ['required', 'in:hold,witness,review'],
        ]);
        $project = Project::where('company_id', $current->id())->findOrFail($data['project_id']);
        $pile = ! empty($data['bored_pile_id']) ? BoredPile::where('project_id', $project->id)->findOrFail($data['bored_pile_id']) : null;
        unset($data['bored_pile_id']);
        $items = [];
        foreach ($data['stage'] as $index => $stage) {
            $items[] = ['stage' => $stage, 'method' => $data['method'][$index], 'acceptance_criteria' => $data['acceptance_criteria'][$index], 'checkpoint_type' => $data['checkpoint_type'][$index]];
        }
        $plan = $service->createPlan($project, $pile, [...$data, 'company_id' => $current->id(), 'items' => $items], $request->user());

        return back()->with('status', "ITP {$plan->number} dibuat dengan ".count($items).' item inspeksi.');
    }

    public function storeInspection(Request $request, ItpItem $item, CurrentCompany $current, ItpService $service)
    {
        abort_unless($item->plan && (int) $item->plan->company_id === $current->id(), 404);
        $data = $request->validate(['performed_at' => ['required', 'date'], 'result' => ['required', 'in:pass,fail,pending'], 'measured_value' => ['nullable', 'max:150'], 'notes' => ['nullable', 'max:2000'], 'inspector_id' => ['required', 'integer']]);
        $this->ensureCompanyUser((int) $data['inspector_id'], $current->id());
        $service->recordInspection($item, $data['performed_at'], $data['result'], $data['measured_value'] ?? null, $data['notes'] ?? null, $current->id(), User::findOrFail($data['inspector_id']), $request->user());

        return back()->with('status', 'Hasil inspeksi dicatat.');
    }

    public function closeItp(Request $request, InspectionTestPlan $plan, CurrentCompany $current, ItpService $service)
    {
        abort_unless($plan->company_id === $current->id(), 404);
        throw_unless(count($service->openHoldPoints($plan)) === 0, ValidationException::withMessages(['hold' => 'Masih ada hold point tanpa hasil pass.']));
        $plan->update(['status' => 'closed']);
        app(AuditTrail::class)->record($current->id(), $request->user()->id, 'qms.itp_closed', $plan);

        return back()->with('status', 'ITP ditutup. Semua hold point tertutup.');
    }
}
