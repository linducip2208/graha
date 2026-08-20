<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\Journal;
use App\Services\AccountingService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function index(CurrentCompany $current)
    {
        return view('finance.index', ['accounts' => Account::where('company_id', $current->id())->orderBy('code')->get(), 'periods' => FiscalPeriod::where('company_id', $current->id())->latest('starts_at')->get(), 'journals' => Journal::where('company_id', $current->id())->with('entries')->latest('journal_date')->paginate(25)]);
    }

    public function account(Request $r, CurrentCompany $current)
    {
        $d = $r->validate(['code' => ['required', 'max:30', 'unique:accounts,code,NULL,id,company_id,'.$current->id()], 'name' => ['required', 'max:200'], 'type' => ['required', 'in:asset,liability,equity,revenue,expense'], 'normal_balance' => ['required', 'in:debit,credit']]);
        Account::create([...$d, 'company_id' => $current->id()]);

        return back()->with('status', 'Akun ditambahkan.');
    }

    public function period(Request $r, CurrentCompany $current)
    {
        $d = $r->validate(['name' => ['required', 'max:100'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after_or_equal:starts_at']]);
        FiscalPeriod::create([...$d, 'company_id' => $current->id()]);

        return back()->with('status', 'Periode fiskal ditambahkan.');
    }

    public function journal(Request $r, CurrentCompany $current, AccountingService $service)
    {
        $d = $r->validate(['journal_date' => ['required', 'date'], 'description' => ['required', 'max:250'], 'debit_account_id' => ['required', 'exists:accounts,id'], 'credit_account_id' => ['required', 'different:debit_account_id', 'exists:accounts,id'], 'amount' => ['required', 'decimal:0,2', 'gt:0'], 'reference' => ['required', 'max:80']]);
        foreach ([$d['debit_account_id'], $d['credit_account_id']] as $id) {
            abort_unless(Account::where('company_id', $current->id())->whereKey($id)->exists(), 422);
        }$service->post($current->id(), $d['journal_date'], 'manual', $d['reference'], $d['description'], [['account_id' => $d['debit_account_id'], 'debit' => $d['amount'], 'credit' => '0'], ['account_id' => $d['credit_account_id'], 'debit' => '0', 'credit' => $d['amount']]], 'manual:'.$d['reference'], $r->user());

        return back()->with('status','Jurnal diposting dan seimbang.');
    }
}
