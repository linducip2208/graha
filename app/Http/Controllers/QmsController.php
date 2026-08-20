<?php

namespace App\Http\Controllers;

use App\Models\CorrectiveAction;
use App\Models\Department;
use App\Models\InternalAudit;
use App\Models\Nonconformity;
use App\Models\RiskOpportunity;
use App\Models\User;
use App\Services\QmsService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class QmsController extends Controller
{
    public function index(CurrentCompany $current)
    {
        $companyId = $current->id();

        return view('qms.index', [
            'risks' => RiskOpportunity::where('company_id', $companyId)->latest()->limit(50)->get(),
            'ncrs' => Nonconformity::where('company_id', $companyId)->with('actions')->latest()->limit(50)->get(),
            'audits' => InternalAudit::where('company_id', $companyId)->latest('scheduled_at')->limit(50)->get(),
            'departments' => Department::where('company_id', $companyId)->orderBy('name')->get(),
            'users' => User::whereHas('companies', fn ($query) => $query->whereKey($companyId))->orderBy('name')->get(),
        ]);
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

    private function ensureCompanyUser(int $userId, int $companyId): void
    {
        abort_unless(User::whereKey($userId)->whereHas('companies', fn ($query) => $query->whereKey($companyId))->exists(), 422);
    }
}
