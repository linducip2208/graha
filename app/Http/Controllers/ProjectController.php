<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BoredPile;
use App\Models\BoredPileDrilling;
use App\Models\BudgetBaseline;
use App\Models\ConcreteDelivery;
use App\Models\ConstraintLog;
use App\Models\ContractChange;
use App\Models\Customer;
use App\Models\Document;
use App\Models\FieldEvidence;
use App\Models\HseIncident;
use App\Models\Item;
use App\Models\MaterialRequest;
use App\Models\Nonconformity;
use App\Models\PileTest;
use App\Models\ProcurementPlan;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\ProjectWbs;
use App\Models\ProjectZone;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\Vendor;
use App\Services\BoredPileService;
use App\Services\PlanningSupportService;
use App\Services\ProjectCostingService;
use App\Services\ProjectHealthService;
use App\Support\Tenancy\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    public function index(Request $request, CurrentCompany $current)
    {
        $companyId = $current->id();
        $view = $request->query('view'); // portfolio (default) | kanban | timeline

        // Saved view via query string: filter status + klien + kata kunci dapat dibagikan sebagai URL.
        $query = Project::where('company_id', $companyId)->withCount('boredPiles')->with('customer:id,code,name')->latest();
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($customer = $request->query('customer')) {
            $query->where('customer_id', $customer);
        }
        if ($term = trim((string) $request->query('q'))) {
            $query->where(fn ($w) => $w->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"));
        }
        $projects = $query->get();

        // Project health dari engine existing (threshold CompanySetting) — tanpa kalkulasi baru.
        $healthMap = app(ProjectHealthService::class)->map($companyId);
        if ($health = $request->query('health')) {
            if (in_array($health, ['green', 'yellow', 'red'], true)) {
                $projects = $projects->filter(fn (Project $p) => ($healthMap[$p->id]['health'] ?? null) === $health)->values();
            }
        }

        $allProjects = Project::where('company_id', $companyId)->withCount('boredPiles')->latest()->get();
        $selected = $allProjects->firstWhere('id', (int) $request->query('project')) ?? $allProjects->first();

        // KPI portofolio dari data riil (tanpa data rekaan).
        $activeProjects = $projects->whereIn('status', ['active', 'in_progress']);
        $physicalValues = $activeProjects->pluck('id')->map(fn ($id) => $healthMap[$id]['physical'] ?? null)->filter();
        $kpi = [
            'total' => $projects->count(),
            'active' => $activeProjects->count(),
            'contract_value' => (float) $projects->sum('contract_value'),
            'critical' => $activeProjects->pluck('id')->count(fn ($id) => ($healthMap[$id]['health'] ?? null) === 'red'),
            'watch' => $activeProjects->pluck('id')->count(fn ($id) => ($healthMap[$id]['health'] ?? null) === 'yellow'),
            'avg_progress' => $physicalValues->isNotEmpty() ? round($physicalValues->avg(), 1) : null,
            'pile_total' => (int) $projects->sum('bored_piles_count'),
        ];

        return view('projects.index', [
            'projects' => $projects,
            'allProjects' => $allProjects,
            'statusCounts' => $allProjects->countBy('status'),
            'customers' => Customer::where('company_id', $companyId)->orderBy('name')->get(['id', 'code', 'name']),
            'healthMap' => $healthMap,
            'kpi' => $kpi,
            'schedule' => $view === 'timeline' && $selected ? $this->buildSchedule($selected) : null,
            'kanban' => $view === 'kanban' ? $this->kanban($projects) : null,
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
        // Health dari engine existing (threshold CompanySetting) — dipakai badge header.
        $data['healthRow'] = app(ProjectHealthService::class)->map($companyId)[$project->id] ?? null;

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
                // EVM ringkas (ADR-051): EV dari progres fisik terkontrak, PV dari jadwal,
                // AC dari project cost ledger. Hanya tampil bila semua sumber tersedia.
                if ($can('finance.view')) {
                    $ev = $contract * $data['physicalPercent'] / 100;
                    $pv = $contract * ($data['plannedPercent'] ?? 0) / 100;
                    $ac = (float) (($data['costing']['actual'] ?? null) ?: 0);
                    if ($ac > 0) {
                        $data['cpi'] = round($ev / $ac, 2);
                        $data['spi'] = $pv > 0 ? round($ev / $pv, 2) : null;
                    }
                }
            }
        }

        if ($tab === 'planning') {
            $data['schedule'] = $this->buildSchedule($project);
            $data['wbsTree'] = PlanningSupportService::wbsTree($project->id);
            $data['constraints'] = ConstraintLog::where('project_id', $project->id)->with(['pile:id,pile_number', 'recorder:id,name'])->orderByRaw("FIELD(status,'open','in_progress','resolved')")->orderBy('raised_at')->limit(50)->get();
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
            $data['plans'] = ProcurementPlan::where('project_id', $project->id)->with(['item:id,name', 'vendor:id,name'])->orderBy('required_date')->limit(60)->get();
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
            $data['baselines'] = BudgetBaseline::where('project_id', $project->id)->with('approver:id,name')->orderByDesc('version')->limit(10)->get();
        }

        if ($tab === 'billing' && $can('finance.view')) {
            $data['billings'] = ProgressBilling::where('project_id', $project->id)->with('journal')->latest('billing_date')->get();
        }

        if ($tab === 'quality' || $tab === 'hse') {
            if ($can('qms.view')) {
                $data['ncrs'] = Nonconformity::where('project_id', $project->id)->with('actions')->latest()->get();
            }
            if ($can('hse.view')) {
                $data['incidents'] = HseIncident::where('project_id', $project->id)->latest()->get();
            }
        }

        if ($tab === 'activity') {
            $pileIds = $piles->pluck('id');
            $data['activity'] = AuditLog::where('company_id', $companyId)
                ->where(function ($q) use ($project, $pileIds) {
                    $q->where(function ($w) use ($project) {
                        $w->where('auditable_type', Project::class)->where('auditable_id', $project->id);
                    })->orWhere(function ($w) use ($pileIds) {
                        $w->where('auditable_type', BoredPile::class)->whereIn('auditable_id', $pileIds);
                    });
                })
                ->with('actor:id,name')
                ->orderByDesc('created_at')
                ->limit(30)
                ->get();
        }

        if ($tab === 'piles') {
            $data['zones'] = $project->zones()->orderBy('code')->get();
        }

        if ($tab === 'documents' && $can('document.view')) {
            $data['documents'] = Document::where('company_id', $companyId)->where('project_id', $project->id)->withCount('versions')->latest()->limit(50)->get();
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

    /** Log kendala rencana (ADR-049). */
    public function storeConstraint(Request $r, Project $project, CurrentCompany $current, PlanningSupportService $service)
    {
        abort_unless($project->company_id === $current->id(), 404);
        $d = $r->validate([
            'type' => ['required', 'max:30'],
            'title' => ['required', 'max:150'],
            'description' => ['required', 'max:2000'],
            'impact_notes' => ['nullable', 'max:1000'],
            'bored_pile_id' => ['nullable', 'integer'],
            'raised_at' => ['required', 'date'],
            'target_date' => ['nullable', 'date', 'after_or_equal:raised_at'],
        ]);
        if (! empty($d['bored_pile_id'])) {
            abort_unless(BoredPile::where('project_id', $project->id)->whereKey($d['bored_pile_id'])->exists(), 422);
        } else {
            unset($d['bored_pile_id']);
        }
        $service->createConstraint($current->id(), [...$d, 'project_id' => $project->id], $r->user());

        return back()->with('status', 'Kendala tercatat di constraint log.');
    }

    public function updateConstraintStatus(Request $r, ConstraintLog $constraint, CurrentCompany $current, PlanningSupportService $service)
    {
        abort_unless($constraint->company_id === $current->id(), 404);
        $d = $r->validate(['status' => ['required', 'in:in_progress,resolved'], 'resolution_notes' => ['nullable', 'max:2000']]);
        $service->updateConstraintStatus($constraint, $d['status'], $d['resolution_notes'] ?? null, $r->user());

        return back()->with('status', 'Status kendala diperbarui.');
    }

    /** Rencana pengadaan proyek (ADR-050). */
    public function storePlan(Request $r, Project $project, CurrentCompany $current, PlanningSupportService $service)
    {
        abort_unless($project->company_id === $current->id(), 404);
        $d = $r->validate([
            'title' => ['required', 'max:180'],
            'item_id' => ['nullable', 'integer'],
            'project_wbs_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'decimal:0,4', 'gt:0'],
            'estimated_value' => ['nullable', 'decimal:0,2', 'min:0'],
            'required_date' => ['required', 'date'],
            'planned_pr_date' => ['nullable', 'date'],
            'planned_po_date' => ['nullable', 'date'],
            'vendor_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'max:1000'],
        ]);
        foreach (['item_id' => Item::class, 'vendor_id' => Vendor::class] as $key => $model) {
            if (! empty($d[$key])) {
                abort_unless($model::where('company_id', $current->id())->whereKey($d[$key])->exists(), 422);
            } else {
                unset($d[$key]);
            }
        }
        if (! empty($d['project_wbs_id'])) {
            abort_unless(ProjectWbs::where('project_id', $project->id)->whereKey($d['project_wbs_id'])->exists(), 422);
        } else {
            unset($d['project_wbs_id']);
        }
        $service->createPlan($current->id(), [...$d, 'project_id' => $project->id], $r->user());

        return back()->with('status', 'Baris rencana pengadaan ditambahkan.');
    }

    public function linkPlanDocument(Request $r, ProcurementPlan $plan, CurrentCompany $current, PlanningSupportService $service)
    {
        abort_unless($plan->company_id === $current->id(), 404);
        $d = $r->validate(['kind' => ['required', 'in:pr,po'], 'document_id' => ['required', 'integer']]);
        $plan = $service->linkDocument($plan, $d['kind'], (int) $d['document_id'], $r->user());

        return back()->with('status', 'Rencana tertaut ke '.strtoupper($d['kind']).' #'.$plan->{ $d['kind'] === 'pr' ? 'purchase_request_id' : 'purchase_order_id' }.'.');
    }

    /** WBS hierarki (ADR-055): parent satu proyek, kedalaman maksimum 4 level. */
    public function storeWbs(Request $r, Project $project, CurrentCompany $current, PlanningSupportService $service)
    {
        abort_unless($project->company_id === $current->id(), 404);
        $d = $r->validate([
            'code' => ['required', 'max:50'],
            'name' => ['required', 'max:180'],
            'budget' => ['nullable', 'decimal:0,2', 'min:0'],
            'parent_id' => ['nullable', 'integer'],
        ]);
        $d['budget'] ??= '0';
        try {
            $service->createWbs($project->id, $d + ['company_id' => $project->company_id], $r->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('status', "WBS {$d['code']} — {$d['name']} ditambahkan.");
    }
}
