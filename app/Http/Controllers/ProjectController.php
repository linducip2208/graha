<?php

namespace App\Http\Controllers;

use App\Models\BoredPile;
use App\Models\BoredPileDrilling;
use App\Models\ConcreteDelivery;
use App\Models\ContractChange;
use App\Models\MaterialRequest;
use App\Models\PileTest;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\ProjectWbs;
use App\Models\ProjectZone;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Services\BoredPileService;
use App\Services\ProjectCostingService;
use App\Support\Tenancy\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function index(Request $request, CurrentCompany $current)
    {
        $companyId = $current->id();

        // Saved view via query string: filter status + kata kunci dapat dibagikan sebagai URL.
        $query = Project::where('company_id', $companyId)->withCount('boredPiles')->latest();
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($term = trim((string) $request->query('q'))) {
            $query->where(fn ($w) => $w->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"));
        }
        $projects = $query->get();
        $allProjects = Project::where('company_id', $companyId)->withCount('boredPiles')->latest()->get();
        $selected = $allProjects->firstWhere('id', (int) $request->query('project')) ?? $allProjects->first();

        return view('projects.index', [
            'projects' => $projects,
            'allProjects' => $allProjects,
            'statusCounts' => $allProjects->countBy('status'),
            'zones' => ProjectZone::whereHas('project', fn ($q) => $q->where('company_id', $companyId))->with('project')->get(),
            'piles' => BoredPile::whereHas('project', fn ($q) => $q->where('company_id', $companyId))->with(['project', 'zone'])->latest()->paginate(30),
            'schedule' => $selected ? $this->buildSchedule($selected) : null,
            'kanban' => $request->query('view') === 'kanban' ? $this->kanban($projects) : null,
        ]);
    }

    /** Papan kanban portofolio proyek per status. */
    private function kanban($projects): array
    {
        $columns = [];
        foreach (['draft' => 'Direncanakan', 'in_progress' => 'Berjalan', 'active' => 'Aktif', 'closed' => 'Selesai'] as $status => $label) {
            $columns[] = ['label' => $label, 'items' => $projects->where('status', $status)->map(fn ($p) => [
                'title' => $p->code.' — '.$p->name,
                'subtitle' => $p->location,
                'meta' => $p->bored_piles_count.' pile',
                'href' => '/admin/projects/'.$p->id,
            ])->values()];
        }

        return $columns;
    }

    /** Workspace detail proyek: satu halaman bertab menggantikan navigasi tersebar. */
    public function show(Request $request, Project $project, CurrentCompany $current)
    {
        abort_unless($project->company_id === $current->id(), 404);
        $companyId = $current->id();
        $user = $request->user();
        $can = fn (string $permission): bool => $user->hasPermission($permission, $companyId);
        $tab = $request->query('tab', 'overview');

        $piles = BoredPile::where('project_id', $project->id)->with(['zone', 'activities'])->orderBy('pile_number')->get();

        $data = ['project' => $project, 'piles' => $piles, 'activeTab' => $tab];

        if ($tab === 'overview') {
            $data['costing'] = $can('finance.view') ? app(ProjectCostingService::class)->summary($project) : null;
            $contract = (float) ($project->contract_value ?: 0);
            $data['physicalPercent'] = 0.0;
            $data['plannedPercent'] = null;
            if ($contract > 0 && $project->planned_start && $project->planned_end) {
                $billed = ProgressBilling::where('project_id', $project->id)->where('status', 'posted')->sum('gross_amount');
                $data['physicalPercent'] = round(min(100.0, (float) $billed * 100 / $contract), 1);
                $totalDays = max(1, $project->planned_start->diffInDays($project->planned_end));
                $elapsed = min($totalDays, max(0, $project->planned_start->diffInDays(now())));
                $data['plannedPercent'] = round($elapsed * 100 / $totalDays, 1);
            }
        }

        if ($tab === 'planning') {
            $data['schedule'] = $this->buildSchedule($project);
            $data['wbs'] = ProjectWbs::where('project_id', $project->id)->orderBy('code')->get();
        }

        if ($tab === 'fieldops' && $can('project.view')) {
            $pileIds = $piles->pluck('id');
            $drillings = BoredPileDrilling::whereIn('bored_pile_id', $pileIds)->get(['id']);
            $deliveries = ConcreteDelivery::where('project_id', $project->id)->get(['id']);
            $tests = PileTest::where('project_id', $project->id)->get(['id']);
            $data['drillings'] = BoredPileDrilling::whereIn('bored_pile_id', $pileIds)->latest('drilling_started_at')->limit(20)->get();
            $data['deliveries'] = ConcreteDelivery::where('project_id', $project->id)->latest('arrived_at')->limit(20)->get();
            $data['tests'] = PileTest::where('project_id', $project->id)->orderByDesc('scheduled_date')->limit(20)->get();
            $data['evidences'] = FieldEvidence::where(function ($q) use ($drillings, $deliveries, $tests): void {
                $q->where(fn ($t) => $t->where('evidence_type', 'drilling')->whereIn('evidence_id', $drillings->pluck('id')))
                    ->orWhere(fn ($t) => $t->where('evidence_type', 'delivery')->whereIn('evidence_id', $deliveries->pluck('id')))
                    ->orWhere(fn ($t) => $t->where('evidence_type', 'test')->whereIn('evidence_id', $tests->pluck('id')));
            })->latest()->limit(30)->get();
        }

        if ($tab === 'materials' && $can('inventory.view')) {
            $data['materialRequests'] = MaterialRequest::with('lines.item')->where('project_id', $project->id)->latest()->get();
        }

        if ($tab === 'procurement' && $can('procurement.view')) {
            $data['purchaseOrders'] = PurchaseOrder::whereHas('purchaseRequest', fn ($q) => $q->where('project_id', $project->id))->with('vendor:id,name')->latest()->limit(25)->get();
            $data['rfqs'] = Rfq::where('project_id', $project->id)->withCount('vendors')->latest()->limit(15)->get();
        }

        if ($tab === 'contracts' && $can('contract.view')) {
            $data['contractChanges'] = ContractChange::where('project_id', $project->id)->with('submitter:id,name')->latest()->get();
        }

        if ($tab === 'cost' && $can('finance.view')) {
            $data['costByCode'] = DB::table('project_cost_ledger')->join('project_cost_codes', 'project_cost_codes.id', '=', 'project_cost_ledger.project_cost_code_id')
                ->where('project_cost_ledger.project_id', $project->id)
                ->selectRaw('project_cost_codes.code, project_cost_codes.name, project_cost_ledger.cost_type, SUM(project_cost_ledger.amount) as total')
                ->groupBy('project_cost_codes.code', 'project_cost_codes.name', 'project_cost_ledger.cost_type')->get();
            $data['costing'] = app(ProjectCostingService::class)->summary($project);
        }

        if ($tab === 'billing' && $can('finance.view')) {
            $data['billings'] = ProgressBilling::where('project_id', $project->id)->with('journal')->latest('billing_date')->get();
        }

        if ($tab === 'quality' && $can('qms.view')) {
            $data['ncrs'] = Nonconformity::where('project_id', $project->id)->with('actions')->latest()->get();
        }

        if ($tab === 'hse' && $can('hse.view')) {
            $data['incidents'] = HseIncident::where('project_id', $project->id)->latest()->get();
        }

        return view('projects.show', $data);
    }

    private function buildSchedule(Project $project): array
    {
        $piles = $project->boredPiles()->with('activities')->orderBy('pile_number')->get()->map(function ($pile) {
            $starts = $pile->activities->min('started_at');
            $ends = $pile->activities->max('finished_at');

            return ['pile' => $pile, 'start' => $starts?->copy(), 'end' => $ends ? Carbon::instance($ends) : null];
        })->values();

        $windowStart = $project->planned_start ?? $piles->map(fn ($row) => $row['start'])->filter()->min() ?? now()->subMonth();
        $windowEnd = $project->planned_end ?? now()->addMonth();
        $windowStart = Carbon::parse($windowStart);
        $windowEnd = Carbon::parse($windowEnd);
        $totalDays = max(1, (int) $windowStart->diffInDays($windowEnd));

        $bars = $piles->filter(fn ($row) => $row['start'])->map(function ($row) use ($windowStart, $totalDays) {
            $end = $row['end'] ?? now();
            $offset = max(0, (int) $windowStart->diffInDays($row['start']));
            $length = max(2, min($totalDays - $offset, (int) ceil($windowStart->copy()->addDays($offset)->diffInDays($end))));

            return ['pile' => $row['pile'], 'left' => round($offset / $totalDays * 100, 2), 'width' => round($length / $totalDays * 100, 2), 'running' => $row['end'] === null];
        })->values();

        $posted = ProgressBilling::where('project_id', $project->id)->where('status', 'posted')->orderBy('billing_date')->get();
        $contractValue = (float) ($project->contract_value ?: 0);
        $curve = collect();
        if ($contractValue > 0 && $project->planned_start && $project->planned_end) {
            $months = [];
            $cursor = Carbon::parse($project->planned_start)->startOfMonth();
            while ($cursor <= $windowEnd && count($months) < 24) {
                $months[] = $cursor->copy();
                $cursor->addMonth();
            }
            $totalPlannedDays = max(1, Carbon::parse($project->planned_start)->diffInDays(Carbon::parse($project->planned_end)));
            $cumulativeActual = '0';
            foreach ($months as $month) {
                $monthEnd = $month->copy()->endOfMonth();
                $plannedFraction = min(1, max(0, Carbon::parse($project->planned_start)->diffInDays($monthEnd) / $totalPlannedDays));
                $cumulativeActual = bcadd($cumulativeActual, (string) $posted->whereBetween('billing_date', [$month->copy(), $monthEnd])->sum('gross_amount'), 2);
                $curve[] = ['label' => $month->translatedFormat('M y'), 'planned' => round($plannedFraction * 100, 2), 'actual' => round((float) bcdiv(bcmul($cumulativeActual, '100', 4), (string) $contractValue, 4), 2)];
            }
        }

        return ['project' => $project, 'bars' => $bars, 'months' => $this->monthTicks($windowStart, $windowEnd), 'curve' => $curve];
    }

    private function monthTicks(Carbon $start, Carbon $end): array
    {
        $ticks = [];
        $cursor = $start->copy()->startOfMonth();
        $count = 0;
        while ($cursor <= $end && $count < 24) {
            $ticks[] = ['label' => $cursor->translatedFormat('M'), 'position' => $count === 0 ? 0.0 : round($start->diffInDays($cursor) / max(1, $start->diffInDays($end)) * 100, 2)];
            $cursor->addMonth();
            $count++;
        }

        return $ticks;
    }

    public function zone(Request $r, CurrentCompany $current)
    {
        $d = $r->validate(['project_id' => ['required', 'exists:projects,id'], 'code' => ['required', 'max:30'], 'name' => ['required', 'max:150']]);
        abort_unless(Project::where('company_id', $current->id())->whereKey($d['project_id'])->exists(), 422);
        ProjectZone::create($d);

        return back()->with('status', 'Zona ditambahkan.');
    }

    public function pile(Request $r, CurrentCompany $current)
    {
        $d = $r->validate(['project_id' => ['required', 'exists:projects,id'], 'project_zone_id' => ['required', 'exists:project_zones,id'], 'pile_number' => ['required', 'max:60'], 'diameter_mm' => ['required', 'decimal:0,2', 'gt:0'], 'planned_depth_m' => ['required', 'decimal:0,3', 'gt:0']]);
        $zone = ProjectZone::where('project_id', $d['project_id'])->whereKey($d['project_zone_id'])->exists();
        abort_unless($zone && Project::where('company_id', $current->id())->whereKey($d['project_id'])->exists(), 422);
        BoredPile::create([...$d, 'created_by' => $r->user()->id]);

        return back()->with('status', 'Titik bored pile ditambahkan.');
    }

    public function transition(Request $r, BoredPile $pile, CurrentCompany $current, BoredPileService $service)
    {
        abort_unless($pile->project()->where('company_id', $current->id())->exists(), 404);
        $d = $r->validate(['status' => ['required', 'string'], 'notes' => ['nullable', 'max:1000']]);
        $service->transition($pile, $d['status'], $r->user(), $d['notes'] ?? null);

        return back()->with('status', 'Status diperbarui.');
    }

    public function concrete(Request $r, BoredPile $pile, CurrentCompany $current, BoredPileService $service)
    {
        abort_unless($pile->project()->where('company_id', $current->id())->exists(), 404);
        $d = $r->validate(['actual_depth_m' => ['required', 'decimal:0,3', 'gt:0'], 'actual_concrete_m3' => ['required', 'decimal:0,4', 'gte:0']]);
        $service->recordConcrete($pile, $d['actual_depth_m'], $d['actual_concrete_m3'], $r->user());

        return back()->with('status', 'Data beton diperbarui.');
    }
}
