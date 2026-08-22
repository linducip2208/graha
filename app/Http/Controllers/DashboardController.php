<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\BoredPile;
use App\Models\Journal;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\StockBalance;
use App\Models\Tender;
use App\Services\ReceivablePayableAgingService;
use App\Support\Tenancy\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request, CurrentCompany $current)
    {
        $companyId = $current->id();
        $user = $request->user();

        $stats = [];
        if ($user->hasPermission('tender.view', $companyId)) {
            $stats['Tender Aktif'] = ['value' => Tender::where('company_id', $companyId)->whereNotIn('status', ['won', 'lost', 'cancelled'])->count(), 'hint' => 'Peluang yang sedang berjalan'];
        }
        if ($user->hasPermission('project.view', $companyId)) {
            $projects = Project::query()->where('company_id', $companyId);
            $stats['Proyek Berjalan'] = ['value' => (clone $projects)->whereIn('status', ['active', 'in_progress'])->count(), 'hint' => 'Nilai kontrak Rp '.number_format((float) (clone $projects)->whereIn('status', ['active', 'in_progress'])->sum('contract_value'), 0, ',', '.')];
        }
        if ($user->hasPermission('approval.view', $companyId)) {
            $pending = ApprovalRequest::where('company_id', $companyId)->where('status', 'pending')->get();
            $mine = $pending->filter(fn ($r) => $r->submitted_by !== $user->id);
            $overdue = $mine->whereNotNull('due_at')->where('due_at', '<', now())->count();
            $stats['Menunggu Persetujuan'] = ['value' => $mine->count(), 'hint' => $overdue > 0 ? "{$overdue} melewati SLA" : 'Semua dalam batas SLA'];
        }

        $aging = null;
        if ($user->hasPermission('finance.view', $companyId)) {
            try {
                $aging = app(ReceivablePayableAgingService::class)->generate($companyId, now());
                $stats['AR Outstanding'] = ['value' => 'Rp '.number_format((float) $aging['ar_total'], 0, ',', '.'), 'hint' => 'Piutang pelanggan belum tertagih'];
            } catch (\Throwable) {
                $aging = null;
            }
        }
        if ($user->hasPermission('inventory.view', $companyId)) {
            $low = StockBalance::where('stock_balances.company_id', $companyId)->join('items', 'items.id', '=', 'stock_balances.item_id')->whereColumn('stock_balances.quantity', '<=', 'items.minimum_stock')->count();
            $stats['Stok Kritis'] = ['value' => $low, 'hint' => 'Item di bawah minimum stock'];
        }

        $revenueTrend = null;
        if ($user->hasPermission('finance.view', $companyId)) {
            $revenueTrend = ProgressBilling::where('company_id', $companyId)->where('status', 'posted')->where('billing_date', '>=', now()->subMonths(5)->startOfMonth())
                ->selectRaw("DATE_FORMAT(billing_date, '%Y-%m') as ym, SUM(gross_amount) as dpp, SUM(tax_amount) as tax")
                ->groupBy('ym')->orderBy('ym')->get()
                ->map(fn ($row) => ['label' => Carbon::createFromFormat('Y-m', $row->ym)->translatedFormat('M y'), 'dpp' => (float) $row->dpp, 'tax' => (float) $row->tax]);
        }

        $pileStatus = null;
        if ($user->hasPermission('project.view', $companyId)) {
            $pileStatus = BoredPile::whereHas('project', fn ($q) => $q->where('company_id', $companyId))->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');
        }

        $approvals = null;
        if ($user->hasPermission('approval.view', $companyId)) {
            $approvals = ApprovalRequest::with('approvable')->where('company_id', $companyId)->where('status', 'pending')->where('submitted_by', '!=', $user->id)->orderBy('due_at')->limit(5)->get();
        }

        $journals = $user->hasPermission('finance.view', $companyId)
            ? Journal::where('company_id', $companyId)->with('entries')->latest('journal_date')->limit(5)->get()
            : collect();

        return view('dashboard', ['company' => $current->get(), 'stats' => $stats, 'revenueTrend' => $revenueTrend, 'pileStatus' => $pileStatus, 'approvals' => $approvals, 'journals' => $journals, 'aging' => $aging]);
    }
}
