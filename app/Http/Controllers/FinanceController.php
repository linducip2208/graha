<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\FiscalPeriod;
use App\Models\Journal;
use App\Services\AccountingService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function index(CurrentCompany $current)
    {
        return view('finance.index', ['accounts' => Account::where('company_id', $current->id())->orderBy('code')->get(), 'mappings' => AccountingMapping::where('company_id', $current->id())->with('account')->orderBy('event_type')->get(), 'periods' => FiscalPeriod::where('company_id', $current->id())->latest('starts_at')->get(), 'journals' => Journal::where('company_id', $current->id())->with('entries')->latest('journal_date')->paginate(25)]);
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
        $data = $request->validate(['event_type' => ['required', 'in:goods_receipt,vendor_invoice,material_issue_project,production_completion'], 'entry_side' => ['required', 'in:debit,credit'], 'account_id' => ['required', 'exists:accounts,id'], 'effective_from' => ['nullable', 'date'], 'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from']]);
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
}
