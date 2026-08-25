<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BoredPile;
use App\Models\JournalEntry;
use App\Models\Nonconformity;
use App\Models\Project;
use App\Models\RiskOpportunity;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Tender;
use App\Services\CashFlowStatementService;
use App\Services\FinancialStatementService;
use App\Services\ManufacturingWipService;
use App\Services\ReceivablePayableAgingService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function executive(Request $request, CurrentCompany $current)
    {
        [$from, $to] = $this->range($request);
        $companyId = $current->id();
        $tenders = Tender::where('company_id', $companyId)->whereBetween('created_at', [$from, $to]);
        $rows = (clone $tenders)->with('customer')->latest()->limit(200)->get();
        $won = (clone $tenders)->where('status', 'won')->count();
        $lost = (clone $tenders)->where('status', 'lost')->count();
        $decided = $won + $lost;

        return view('reports.index', [
            'title' => 'Laporan Bisnis & Tender', 'type' => 'executive', 'from' => $from, 'to' => $to, 'rows' => $rows,
            'cards' => ['Tender' => (clone $tenders)->count(), 'Menang' => $won, 'Kalah' => $lost, 'Win Rate %' => $decided ? round($won / $decided * 100, 2) : 0],
        ]);
    }

    public function finance(Request $request, CurrentCompany $current)
    {
        [$from, $to] = $this->range($request);
        $entries = JournalEntry::query()->whereHas('journal', fn (Builder $query) => $query->where('company_id', $current->id())->whereBetween('journal_date', [$from->toDateString(), $to->toDateString()]))->with(['journal', 'account'])->limit(500)->get();
        $debit = $entries->reduce(fn (string $carry, JournalEntry $entry) => bcadd($carry, $entry->debit, 2), '0');
        $credit = $entries->reduce(fn (string $carry, JournalEntry $entry) => bcadd($carry, $entry->credit, 2), '0');

        return view('reports.index', [
            'title' => 'Laporan Keuangan', 'type' => 'finance', 'from' => $from, 'to' => $to, 'rows' => $entries,
            'cards' => ['Total Debit' => $debit, 'Total Kredit' => $credit, 'Jurnal' => $entries->pluck('journal_id')->unique()->count(), 'Akun Aktif' => Account::where('company_id', $current->id())->where('is_active', true)->count()],
        ]);
    }

    public function operations(Request $request, CurrentCompany $current)
    {
        [$from, $to] = $this->range($request);
        $movements = StockMovement::where('company_id', $current->id())->whereBetween('posted_at', [$from, $to])->with('item')->latest('posted_at')->limit(500)->get();

        return view('reports.index', [
            'title' => 'Laporan Operasional', 'type' => 'operations', 'from' => $from, 'to' => $to, 'rows' => $movements,
            'cards' => [
                'Proyek' => Project::where('company_id', $current->id())->count(),
                'Titik Bored Pile' => BoredPile::whereHas('project', fn (Builder $query) => $query->where('company_id', $current->id()))->count(),
                'Saldo Stok Aktif' => StockBalance::where('company_id', $current->id())->where('quantity', '>', 0)->count(),
                'NCR Terbuka' => Nonconformity::where('company_id', $current->id())->where('status', '!=', 'closed')->count(),
                'Risiko Terbuka' => RiskOpportunity::where('company_id', $current->id())->where('status', 'open')->count(),
            ],
        ]);
    }

    public function manufacturing(Request $request, CurrentCompany $current, ManufacturingWipService $service)
    {
        [$from, $to] = $this->range($request);
        $rows = $service->reconcile($current->id());

        return view('reports.manufacturing', [
            'from' => $from, 'to' => $to, 'rows' => $rows,
            'totalActual' => $rows->reduce(fn (string $carry, array $row) => bcadd($carry, $row['actual_cost'], 2), '0'),
            'totalWip' => $rows->reduce(fn (string $carry, array $row) => bcadd($carry, $row['residual_wip'], 2), '0'),
            'anomalies' => $rows->where('anomaly', true)->count(),
        ]);
    }

    public function financialStatements(Request $request, CurrentCompany $current, FinancialStatementService $service)
    {
        [$from, $to] = $this->range($request);

        return view('reports.financial-statements', [...$service->generate($current->id(), $from->toDateString(), $to->toDateString()), 'from' => $from, 'to' => $to]);
    }

    public function cashFlow(Request $request, CurrentCompany $current, CashFlowStatementService $service)
    {
        [$from, $to] = $this->range($request);
        try {
            $report = [...$service->generate($current->id(), $from->toDateString(), $to->toDateString()), 'from' => $from, 'to' => $to, 'error' => null];
        } catch (ValidationException $e) {
            $report = ['from' => $from, 'to' => $to, 'error' => $e->getMessage()];
        }

        return view('reports.cash-flow', $report);
    }

    public function aging(Request $request, CurrentCompany $current, ReceivablePayableAgingService $service)
    {
        $data = $request->validate(['as_of' => ['nullable', 'date']]);
        $asOf = Carbon::parse($data['as_of'] ?? now()->toDateString())->endOfDay();

        return view('reports.aging', [...$service->generate($current->id(), $asOf), 'asOf' => $asOf]);
    }

    public function export(Request $request, CurrentCompany $current, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['executive', 'operations', 'manufacturing'], true), 404);
        [$from, $to] = $this->range($request);
        $companyId = $current->id();

        return response()->streamDownload(function () use ($type, $from, $to, $companyId): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            if ($type === 'executive') {
                fputcsv($output, ['Nomor', 'Nama', 'Status', 'Nilai Penawaran', 'Tanggal']);
                Tender::where('company_id', $companyId)->whereBetween('created_at', [$from, $to])->orderBy('id')->chunk(500, function ($items) use ($output): void {
                    foreach ($items as $item) {
                        fputcsv($output, [$item->number, $item->name, $item->status, $item->bid_value, $item->created_at]);
                    }
                });
            } elseif ($type === 'operations') {
                fputcsv($output, ['Waktu', 'Tipe', 'Item', 'Qty', 'Saldo', 'Referensi']);
                StockMovement::where('company_id', $companyId)->whereBetween('posted_at', [$from, $to])->with('item')->orderBy('id')->chunk(500, function ($items) use ($output): void {
                    foreach ($items as $item) {
                        fputcsv($output, [$item->posted_at, $item->movement_type, $item->item?->sku, $item->quantity, $item->balance_after, $item->reference_type.':'.$item->reference_id]);
                    }
                });
            } else {
                fputcsv($output, ['Production Order', 'Produk', 'Rencana', 'Selesai', 'Biaya Aktual', 'Ke Barang Jadi', 'Ke Scrap', 'Residual WIP', 'Status Rekonsiliasi']);
                foreach (app(ManufacturingWipService::class)->reconcile($companyId) as $row) {
                    $order = $row['order'];
                    fputcsv($output, [$order->number, $order->bom?->outputItem?->name, $order->planned_quantity, $order->completed_quantity, $row['actual_cost'], $row['completed_cost'], $row['scrapped_cost'], $row['residual_wip'], $row['anomaly'] ? 'ANOMALI' : 'OK']);
                }
            }
            fclose($output);
        }, "laporan-$type-{$from->toDateString()}-{$to->toDateString()}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function range(Request $request): array
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = Carbon::parse($data['from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($data['to'] ?? now()->toDateString())->endOfDay();

        return [$from, $to];
    }
}
