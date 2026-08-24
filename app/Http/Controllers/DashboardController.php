<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\BoredPile;
use App\Models\CompanyExperience;
use App\Models\CompanySetting;
use App\Models\CorrectiveAction;
use App\Models\HseIncident;
use App\Models\JobSafetyAnalysis;
use App\Models\Journal;
use App\Models\Nonconformity;
use App\Models\ProcurementPlan;
use App\Models\ProductionOrder;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\ReinforcementCage;
use App\Models\Rfq;
use App\Models\StockBalance;
use App\Models\Tender;
use App\Services\ProjectCostingService;
use App\Services\ReceivablePayableAgingService;
use App\Support\Tenancy\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request, CurrentCompany $current)
    {
        $companyId = $current->id();
        $user = $request->user();
        $can = fn (string $permission): bool => $user->hasPermission($permission, $companyId);

        $stats = [];
        if ($can('tender.view')) {
            $stats['Tender Aktif'] = ['value' => Tender::where('company_id', $companyId)->whereNotIn('status', ['won', 'lost', 'cancelled'])->count(), 'hint' => 'Peluang yang sedang berjalan'];
        }
        if ($can('project.view')) {
            $projects = Project::query()->where('company_id', $companyId);
            $stats['Proyek Berjalan'] = ['value' => (clone $projects)->whereIn('status', ['active', 'in_progress'])->count(), 'hint' => 'Nilai kontrak Rp '.number_format((float) (clone $projects)->whereIn('status', ['active', 'in_progress'])->sum('contract_value'), 0, ',', '.')];
        }
        if ($can('approval.view')) {
            $pending = ApprovalRequest::where('company_id', $companyId)->where('status', 'pending')->get();
            $mine = $pending->filter(fn ($r) => $r->submitted_by !== $user->id);
            $overdue = $mine->whereNotNull('due_at')->where('due_at', '<', now())->count();
            $stats['Menunggu Persetujuan'] = ['value' => $mine->count(), 'hint' => $overdue > 0 ? "{$overdue} melewati SLA" : 'Semua dalam batas SLA'];
        }

        $aging = null;
        if ($can('finance.view')) {
            try {
                $aging = app(ReceivablePayableAgingService::class)->generate($companyId, now());
                $stats['AR Outstanding'] = ['value' => 'Rp '.number_format((float) $aging['ar_total'], 0, ',', '.'), 'hint' => 'Piutang pelanggan belum tertagih'];
            } catch (\Throwable) {
                $aging = null;
            }
        }
        if ($can('inventory.view')) {
            $low = StockBalance::where('stock_balances.company_id', $companyId)->join('items', 'items.id', '=', 'stock_balances.item_id')->whereColumn('stock_balances.quantity', '<=', 'items.minimum_stock')->count();
            $stats['Stok Kritis'] = ['value' => $low, 'hint' => 'Item di bawah minimum stock'];
        }
        if ($can('qms.view')) {
            $openNcr = Nonconformity::where('company_id', $companyId)->whereIn('status', ['open', 'containment'])->count();
            $overdueCapa = CorrectiveAction::where('status', 'open')->whereDate('due_at', '<', now())->count();
            $stats['NCR Terbuka'] = ['value' => $openNcr, 'hint' => $overdueCapa > 0 ? "{$overdueCapa} CAPA lewat tenggat" : 'CAPA dalam kendali'];
        }
        if ($can('hse.view')) {
            $openIncidents = HseIncident::where('company_id', $companyId)->whereNotIn('status', ['closed'])->count();
            $jsaActive = JobSafetyAnalysis::where('company_id', $companyId)->where('valid_until', '>=', today())->count();
            $stats['Incident Terbuka'] = ['value' => $openIncidents, 'hint' => "JSA aktif: {$jsaActive}"];
        }
        if ($can('manufacturing.view')) {
            $activeOrders = ProductionOrder::where('company_id', $companyId)->whereNotIn('status', ['completed', 'completed_with_scrap', 'cancelled'])->count();
            $cagesPending = ReinforcementCage::where('company_id', $companyId)->where('qc_status', 'draft')->count();
            $stats['Order Produksi Aktif'] = ['value' => $activeOrders, 'hint' => "Cage menunggu QC: {$cagesPending}"];
        }

        $revenueTrend = null;
        if ($can('finance.view')) {
            $revenueTrend = ProgressBilling::where('company_id', $companyId)->where('status', 'posted')->where('billing_date', '>=', now()->subMonths(5)->startOfMonth())
                ->selectRaw("DATE_FORMAT(billing_date, '%Y-%m') as ym, SUM(gross_amount) as dpp, SUM(tax_amount) as tax")
                ->groupBy('ym')->orderBy('ym')->get()
                ->map(fn ($row) => ['label' => Carbon::createFromFormat('Y-m', $row->ym)->translatedFormat('M y'), 'dpp' => (float) $row->dpp, 'tax' => (float) $row->tax]);
        }

        $pileStatus = null;
        if ($can('project.view')) {
            $pileStatus = BoredPile::whereHas('project', fn ($q) => $q->where('company_id', $companyId))->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');
        }

        $approvals = null;
        if ($can('approval.view')) {
            $approvals = ApprovalRequest::with('approvable')->where('company_id', $companyId)->where('status', 'pending')->where('submitted_by', '!=', $user->id)->orderBy('due_at')->limit(5)->get();
        }

        $journals = $can('finance.view')
            ? Journal::where('company_id', $companyId)->with('entries')->latest('journal_date')->limit(5)->get()
            : collect();

        // ===== Role-profile sections =====
        $executive = $this->executiveCockpit($companyId, $can);
        $projectHealth = $can('project.view') ? $this->projectHealth($companyId) : collect();
        $procurementQueue = $can('procurement.view') ? [
            'rfqOpen' => Rfq::where('company_id', $companyId)->where('status', 'open')->count(),
            'poPendingReceive' => PurchaseOrder::where('company_id', $companyId)->whereIn('status', ['approved', 'issued'])->count(),
            'poValue' => PurchaseOrder::where('company_id', $companyId)->whereIn('status', ['approved', 'issued', 'partially_received', 'received'])->sum('total'),
        ] : null;

        // Dashboard Builder (ADR-063): susun ulang $stats sesuai config company
        // (id widget = label stat di registry). Tanpa config -> layout legacy.
        $widgetConfig = CompanyExperience::find($companyId)?->dashboard_config;
        $widths = [];
        if (is_array($widgetConfig) && $widgetConfig !== []) {
            $registry = config('dashboard-widgets');
            $ordered = collect($widgetConfig)
                ->filter(fn ($w) => isset($registry[$w['id']]))
                ->filter(fn ($w) => ($perm = $registry[$w['id']]['permission']) === null || $can($perm))
                ->pluck('id');
            $stats = collect($ordered)
                ->mapWithKeys(fn ($id) => [$registry[$id]['label'] => $stats[$registry[$id]['label']] ?? ['value' => '0', 'hint' => '']])
                ->union(collect($stats)->diffKeys(collect($ordered)->mapWithKeys(fn ($id) => [$registry[$id]['label'] => true])->all()))
                ->all();
            foreach ($ordered as $id) {
                $widths[$registry[$id]['label']] = $registry[$id]['width'];
            }
        }

        // Attention Required (komposisi ulang metrik existing — tanpa fitur baru).
        $attention = collect();
        if ($can('approval.view')) {
            $attention->push(['label' => 'Menunggu Persetujuan', 'count' => $approvals?->count() ?? 0, 'icon' => 'check', 'tone' => 'warning', 'href' => '/admin/approvals']);
        }
        if ($can('qms.view')) {
            $capaOverdue = CorrectiveAction::where('status', 'open')->whereDate('due_at', '<', now())->count();
            $attention->push(['label' => 'NCR Terbuka', 'count' => Nonconformity::where('company_id', $companyId)->whereIn('status', ['open', 'containment'])->count(), 'icon' => 'shield', 'tone' => 'danger', 'href' => '/admin/qms']);
            if ($capaOverdue > 0) {
                $attention->push(['label' => 'CAPA Lewat Tenggat', 'count' => $capaOverdue, 'icon' => 'clock', 'tone' => 'danger', 'href' => '/admin/qms']);
            }
        }
        if ($can('procurement.view')) {
            $poLate = ProcurementPlan::where('company_id', $companyId)
                ->whereNull('purchase_request_id')->whereNull('purchase_order_id')
                ->whereNotNull('planned_po_date')->where('planned_po_date', '<', now()->toDateString())->count();
            if ($poLate > 0) {
                $attention->push(['label' => 'Pengadaan Terlambat', 'count' => $poLate, 'icon' => 'cart', 'tone' => 'warning', 'href' => '/admin/projects']);
            }
        }
        $critical = $projectHealth->where('health', 'red')->count();
        if ($can('project.view') && $critical > 0) {
            $attention->push(['label' => 'Proyek Kritis', 'count' => $critical, 'icon' => 'triangle-alert', 'tone' => 'danger', 'href' => '/admin/projects']);
        }
        if ($attention->isEmpty()) {
            $attention->push(['label' => 'Semua dalam kendali', 'count' => 0, 'icon' => 'check', 'tone' => 'success', 'href' => null]);
        }

        // Recent activity dari audit trail existing.
        $activity = $can('audit.view')
            ? AuditLog::where('company_id', $companyId)->with('actor:id,name')->latest('created_at')->limit(7)->get()
            : collect();

        return view('dashboard', [
            'company' => $current->get(), 'stats' => $stats, 'revenueTrend' => $revenueTrend, 'pileStatus' => $pileStatus,
            'approvals' => $approvals, 'journals' => $journals, 'aging' => $aging,
            'executive' => $executive, 'projectHealth' => $projectHealth, 'procurementQueue' => $procurementQueue,
            'widths' => $widths, 'attention' => $attention, 'activity' => $activity,
        ]);
    }

    /** Executive cockpit: revenue MTD/YTD, cash, AR/AP, kontrak, win rate. */
    private function executiveCockpit(int $companyId, callable $can): ?array
    {
        if (! $can('finance.view') || ! $can('report.view')) {
            return null;
        }
        $billing = ProgressBilling::where('company_id', $companyId)->where('status', 'posted');
        $revenueMtd = (clone $billing)->whereBetween('billing_date', [now()->startOfMonth(), now()])->sum('gross_amount');
        $revenueYtd = (clone $billing)->whereBetween('billing_date', [now()->startOfYear(), now()])->sum('gross_amount');
        $costYtd = (float) DB::table('project_cost_ledger')->join('projects', 'projects.id', '=', 'project_cost_ledger.project_id')
            ->where('projects.company_id', $companyId)->where('project_cost_ledger.cost_type', 'actual')
            ->whereYear('project_cost_ledger.cost_date', now()->year)->sum('project_cost_ledger.amount');

        return [
            'revenue_mtd' => (float) $revenueMtd,
            'revenue_ytd' => (float) $revenueYtd,
            'gp_ytd' => (float) ($revenueYtd - $costYtd),
            'contract_active' => (float) Project::where('company_id', $companyId)->whereIn('status', ['active', 'in_progress'])->sum('contract_value'),
            'win_rate' => $this->winRate($companyId),
        ];
    }

    private function winRate(int $companyId): ?float
    {
        $won = (int) Tender::where('company_id', $companyId)->where('status', 'won')->count();
        $lost = (int) Tender::where('company_id', $companyId)->where('status', 'lost')->count();

        return ($won + $lost) > 0 ? round($won * 100 / ($won + $lost), 1) : null;
    }

    /** Project health: physical vs planned %, EAC variance, status hijau/kuning/merah via threshold configurable. */
    private function projectHealth(int $companyId): Collection
    {
        $yellow = max(1.0, (float) CompanySetting::val($companyId, 'project_health_yellow_percent'));
        $red = max($yellow + 0.1, (float) CompanySetting::val($companyId, 'project_health_red_percent'));

        $projects = Project::where('company_id', $companyId)->whereIn('status', ['active', 'in_progress'])->orderBy('code')->get();
        if ($projects->isEmpty()) {
            return collect();
        }

        // Batch aggregate: hindari N+1 pada portofolio (1 kueri per agregat, bukan per proyek).
        $billedByProject = ProgressBilling::whereIn('project_id', $projects->pluck('id'))->where('status', 'posted')
            ->groupBy('project_id')->selectRaw('project_id, SUM(gross_amount) as total')->pluck('total', 'project_id');
        $summaries = app(ProjectCostingService::class)->summariesFor($projects);

        return $projects->map(function (Project $project) use ($summaries, $billedByProject, $yellow, $red) {
            $summary = $summaries[$project->id];
            $contract = (float) ($project->contract_value ?: 0);
            $physical = 0.0;
            if ($contract > 0 && $project->planned_start && $project->planned_end) {
                $totalDays = max(1, $project->planned_start->diffInDays($project->planned_end));
                $elapsedDays = min($totalDays, max(0, $project->planned_start->diffInDays(now())));
                $billed = (float) ($billedByProject[$project->id] ?? 0);
                $physical = round(min(100.0, $billed * 100 / $contract), 1);
                $planned = round($elapsedDays * 100 / $totalDays, 1);
            } else {
                $planned = 100.0;
            }
            $variancePct = $physical - $planned;
            $health = abs($variancePct) >= $red ? 'red' : (abs($variancePct) >= $yellow ? 'yellow' : 'green');
            $margin = bccomp((string) $contract, '0', 2) === 1
                ? round((float) bcdiv(bcmul(bcsub((string) $contract, $summary['eac'], 2), '100', 4), (string) $contract, 4), 1)
                : null;

            return ['project' => $project, 'physical' => $physical, 'planned' => $planned, 'variance' => $variancePct, 'eac' => (float) $summary['eac'], 'margin' => $margin, 'health' => $health];
        });
    }
}
