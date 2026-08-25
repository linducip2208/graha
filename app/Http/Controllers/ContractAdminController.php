<?php

namespace App\Http\Controllers;

use App\Models\ContractInsurance;
use App\Models\ContractMilestone;
use App\Models\ProjectAward;
use App\Services\ContractAdminService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

/** Administrasi kontrak: register milestone progres dan asuransi per kontrak (award). */
class ContractAdminController extends Controller
{
    public function index(Request $request, CurrentCompany $current)
    {
        $awards = ProjectAward::where('company_id', $current->id())->with('customer')->orderByDesc('award_date')->limit(100)->get();
        $selectedId = (int) ($request->query('award_id') ?? 0);
        $selected = $awards->firstWhere('id', $selectedId) ?? $awards->first();
        [$milestones, $insurances] = $selected
            ? [ContractMilestone::where('project_award_id', $selected->id)->orderBy('planned_date')->get(), ContractInsurance::where('project_award_id', $selected->id)->orderBy('end_date')->get()]
            : [collect(), collect()];
        $weightUsed = (string) $milestones->sum('weight_percent');

        return view('contract-admin.index', [
            'awards' => $awards,
            'selected' => $selected,
            'milestones' => $milestones,
            'insurances' => $insurances,
            'weightUsed' => $weightUsed,
            'stats' => [
                'contracts' => $awards->count(),
                'milestones' => $milestones->count(),
                'achieved' => $milestones->where('status', 'achieved')->count(),
                'late' => $milestones->filter(fn (ContractMilestone $m) => $m->isLate())->count(),
                'insuranceExpiring' => $insurances->filter(fn (ContractInsurance $i) => in_array($i->statusNow(), ['expiring', 'expired'], true))->count(),
            ],
            'progress' => bccomp($weightUsed, '0', 3) === 1 ? bcdiv((string) $milestones->where('status', 'achieved')->sum('weight_percent'), $weightUsed, 4) : null,
        ]);
    }

    public function storeMilestone(Request $request, ProjectAward $award, CurrentCompany $current, ContractAdminService $service)
    {
        abort_unless($award->company_id === $current->id(), 404);
        $data = $request->validate(['name' => ['required', 'max:200'], 'planned_date' => ['nullable', 'date'], 'actual_date' => ['nullable', 'date'], 'weight_percent' => ['required', 'decimal:0,3', 'between:0.001,100'], 'amount' => ['required', 'decimal:0,2', 'min:0'], 'notes' => ['nullable', 'max:1000']]);
        if (! empty($data['actual_date'])) {
            $data['status'] = 'achieved';
        }
        $service->addMilestone($award, [...$data, 'company_id' => $current->id()], $request->user());

        return back()->with('status', 'Milestone ditambahkan.');
    }

    public function achieveMilestone(Request $request, ContractMilestone $milestone, CurrentCompany $current, ContractAdminService $service)
    {
        abort_unless($milestone->company_id === $current->id(), 404);
        $data = $request->validate(['actual_date' => ['required', 'date']]);
        $service->achieveMilestone($milestone, $data['actual_date'], $request->user());

        return back()->with('status', 'Milestone dicapai.');
    }

    public function storeInsurance(Request $request, ProjectAward $award, CurrentCompany $current, ContractAdminService $service)
    {
        abort_unless($award->company_id === $current->id(), 404);
        $data = $request->validate(['policy_number' => ['required', 'max:120'], 'provider' => ['required', 'max:150'], 'coverage_type' => ['required', 'in:car,ear,tpl,surety,other'], 'insured_amount' => ['required', 'decimal:0,2', 'gt:0'], 'premium' => ['required', 'decimal:0,2', 'min:0'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date'], 'notes' => ['nullable', 'max:1000']]);
        $service->addInsurance($award, [...$data, 'company_id' => $current->id()], $request->user());

        return back()->with('status', 'Polis asuransi terdaftar.');
    }
}
