<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\FiscalPeriod;
use App\Models\Journal;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\RecurringJournal;
use App\Models\VendorInvoice;
use App\Services\AccountingService;
use App\Services\CashFlowForecastService;
use App\Services\ReceivablePayableAgingService;
use App\Services\RecurringJournalService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    /** Ikhtisar keuangan: ringkasan posisi kas, AR/AP, billing, dan tautan modul. */
    public function overview(CurrentCompany $current)
    {
        $companyId = $current->id();
        $billing = ProgressBilling::where('company_id', $companyId)->where('status', 'posted');
        $aging = null;

        try {
            $aging = app(ReceivablePayableAgingService::class)->generate($companyId, now());
        } catch (\Throwable) {
            $aging = null;
        }

        return view('finance.overview', [
            'revenueMtd' => (float) (clone $billing)->whereBetween('billing_date', [now()->startOfMonth(), now()])->sum('gross_amount'),
            'revenueYtd' => (float) (clone $billing)->whereBetween('billing_date', [now()->startOfYear(), now()])->sum('gross_amount'),
            'arOutstanding' => $aging['ar_total'] ?? 0,
            'apOutstanding' => $aging['ap_total'] ?? 0,
            'cashBalance' => (float) DB::table('journal_entries')
                ->join('bank_accounts', 'bank_accounts.account_id', '=', 'journal_entries.account_id')
                ->where('bank_accounts.company_id', $companyId)
                ->selectRaw('COALESCE(SUM(journal_entries.debit - journal_entries.credit), 0) as bal')->value('bal'),
            'pendingBillings' => ProgressBilling::where('company_id', $companyId)->whereIn('status', ['draft', 'pending_approval'])->count(),
            'openVendorInvoices' => VendorInvoice::where('company_id', $companyId)->whereNotIn('match_status', ['posted'])->count(),
            'recentJournals' => Journal::where('company_id', $companyId)->with('entries')->latest('journal_date')->limit(8)->get(),
            'forecast' => $aging ? app(CashFlowForecastService::class)->forecast($companyId) : null,
        ]);
    }

    public function index(CurrentCompany $current)
    {
        return view('finance.index', ['accounts' => Account::where('company_id', $current->id())->orderBy('code')->get(), 'mappings' => AccountingMapping::where('company_id', $current->id())->with('account')->orderBy('event_type')->get(), 'periods' => FiscalPeriod::where('company_id', $current->id())->latest('starts_at')->get(), 'journals' => Journal::where('company_id', $current->id())->with('entries')->latest('journal_date')->paginate(25)]);
    }

    public function accounts(Request $request, CurrentCompany $current)
    {
        $query = Account::where('company_id', $current->id())->orderBy('code');
        if ($term = trim((string) $request->query('q'))) {
            $query->where(fn ($w) => $w->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"));
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->query('status')) {
            $query->where('is_active', $status === 'active');
        }

        return view('finance.accounts', ['accounts' => $query->paginate(50)->withQueryString()]);
    }

    public function periods(CurrentCompany $current)
    {
        return view('finance.periods', ['periods' => FiscalPeriod::where('company_id', $current->id())->latest('starts_at')->paginate(30)]);
    }

    public function journals(CurrentCompany $current)
    {
        return view('finance.journals', ['accounts' => Account::where('company_id', $current->id())->where('is_active', true)->orderBy('code')->get(), 'journals' => Journal::where('company_id', $current->id())->with('entries')->latest('journal_date')->paginate(50)]);
    }

    public function account(Request $r, CurrentCompany $current)
    {
        $d = $r->validate(['code' => ['required', 'max:30', 'unique:accounts,code,NULL,id,company_id,'.$current->id()], 'name' => ['required', 'max:200'], 'type' => ['required', 'in:asset,liability,equity,revenue,expense'], 'normal_balance' => ['required', 'in:debit,credit']]);
        Account::create([...$d, 'company_id' => $current->id()]);

        return back()->with('status', 'Akun ditambahkan.');
    }

    public function mappingIndex(CurrentCompany $current)
    {
        return view('finance.mappings', ['accounts' => Account::where('company_id', $current->id())->where('is_active', true)->orderBy('code')->get(), 'mappings' => AccountingMapping::where('company_id', $current->id())->with('account')->orderBy('event_type')->get()]);
    }

    public function period(Request $r, CurrentCompany $current)
    {
        $d = $r->validate(['name' => ['required', 'max:100'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after_or_equal:starts_at']]);
        FiscalPeriod::create([...$d, 'company_id' => $current->id()]);

        return back()->with('status', 'Periode fiskal ditambahkan.');
    }

    public function mapping(Request $request, CurrentCompany $current)
    {
        $data = $request->validate(['event_type' => ['required', 'in:goods_receipt,vendor_invoice,material_issue_project,material_issue_manufacturing,production_completion,production_scrap,production_conversion_cost,progress_billing,customer_receipt,vendor_payment,retention_release,asset_depreciation'], 'entry_side' => ['required', 'in:debit,credit,ar_debit,retention_debit,advance_debit,revenue_credit,ar_credit,ap_debit,retention_credit,expense_debit,accumulated_credit,wip_debit,raw_credit,finished_goods_debit,wip_credit,scrap_expense_debit,labor_absorption_credit,overhead_absorption_credit'], 'account_id' => ['required', 'exists:accounts,id'], 'effective_from' => ['nullable', 'date'], 'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from']]);
        abort_unless(Account::where('company_id', $current->id())->whereKey($data['account_id'])->exists(), 422);
        AccountingMapping::updateOrCreate(['company_id' => $current->id(), 'event_type' => $data['event_type'], 'entry_side' => $data['entry_side']], $data);

        return back()->with('status', 'Accounting mapping diperbarui.');
    }

    public function journal(Request $r, CurrentCompany $current, AccountingService $service)
    {
        $d = $r->validate(['journal_date' => ['required', 'date'], 'description' => ['required', 'max:250'], 'debit_account_id' => ['required', 'exists:accounts,id'], 'credit_account_id' => ['required', 'different:debit_account_id', 'exists:accounts,id'], 'amount' => ['required', 'decimal:0,2', 'gt:0'], 'reference' => ['required', 'max:80']]);
        foreach ([$d['debit_account_id'], $d['credit_account_id']] as $id) {
            abort_unless(Account::where('company_id', $current->id())->whereKey($id)->exists(), 422);
        }$service->post($current->id(), $d['journal_date'], 'manual', $d['reference'], $d['description'], [['account_id' => $d['debit_account_id'], 'debit' => $d['amount'], 'credit' => '0'], ['account_id' => $d['credit_account_id'], 'debit' => '0', 'credit' => $d['amount']]], 'manual:'.$d['reference'], $r->user());

        return back()->with('status', 'Jurnal diposting dan seimbang.');
    }

    public function recurringJournals(CurrentCompany $current)
    {
        $templates = RecurringJournal::where('company_id', $current->id())->orderBy('name')->get();

        return view('finance.recurring-journals', [
            'templates' => $templates,
            'accounts' => Account::where('company_id', $current->id())->where('is_active', true)->orderBy('code')->get(),
            'projects' => Project::where('company_id', $current->id())->whereIn('status', ['active', 'in_progress'])->orderBy('code')->get(),
            'stats' => ['total' => $templates->count(), 'active' => $templates->where('status', 'active')->count(), 'due' => $templates->where('status', 'active')->filter(fn ($t) => $t->next_run_at->isPast())->count()],
        ]);
    }

    public function storeRecurringJournal(Request $request, CurrentCompany $current, RecurringJournalService $service)
    {
        $data = $request->validate([
            'name' => ['required', 'max:120'], 'description' => ['required', 'max:255'],
            'day_of_month' => ['required', 'integer', 'min:1', 'max:28'],
            'account_id' => ['required', 'array', 'min:2'], 'account_id.*' => ['required', 'integer'],
            'debit' => ['nullable', 'array'], 'debit.*' => ['nullable', 'decimal:0,2', 'min:0'],
            'credit' => ['nullable', 'array'], 'credit.*' => ['nullable', 'decimal:0,2', 'min:0'],
            'project_id_row' => ['nullable', 'array'], 'project_id_row.*' => ['nullable', 'integer'],
        ]);
        $lines = [];
        foreach ($data['account_id'] as $index => $accountId) {
            $debit = (string) ($data['debit'][$index] ?? '0');
            $credit = (string) ($data['credit'][$index] ?? '0');
            if (bccomp($debit, '0', 2) === 0 && bccomp($credit, '0', 2) === 0 && trim((string) $accountId) === '') {
                continue;
            }
            $lines[] = ['account_id' => $accountId, 'debit' => bccomp($debit, '0', 2) === 1 ? $debit : '0', 'credit' => bccomp($credit, '0', 2) === 1 ? $credit : '0'] + (! empty($data['project_id_row'][$index]) ? ['project_id' => (int) $data['project_id_row'][$index]] : []);
        }
        $template = $service->create($current->id(), [...$data, 'lines' => $lines], $request->user());

        return back()->with('status', "Template {$template->name} dibuat. Posting otomatis tiap tanggal {$template->day_of_month}.");
    }

    public function toggleRecurringJournal(RecurringJournal $recurring, CurrentCompany $current)
    {
        abort_unless($recurring->company_id === $current->id(), 404);
        $recurring->update(['status' => $recurring->status === 'active' ? 'paused' : 'active']);

        return back()->with('status', 'Template '.($recurring->status === 'active' ? 'diaktifkan.' : 'dijeda.'));
    }

    public function runRecurringJournalNow(Request $request, RecurringJournal $recurring, CurrentCompany $current, RecurringJournalService $service)
    {
        abort_unless($recurring->company_id === $current->id(), 404);
        $service->runNow($recurring, $request->user());

        return back()->with('status', "Template '{$recurring->name}' diposting hari ini (idempotent per periode).");
    }
}
