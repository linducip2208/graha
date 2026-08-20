<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\FiscalPeriod;
use App\Models\ProgressBilling;
use App\Models\VendorInvoice;
use App\Services\CashBankService;
use App\Services\FiscalPeriodClosingService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class CashBankController extends Controller
{
    public function index(CurrentCompany $current)
    {
        $companyId = $current->id();

        return view('cash-bank.index', [
            'accounts' => Account::where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(),
            'banks' => BankAccount::where('company_id', $companyId)->orderBy('code')->get(),
            'billings' => ProgressBilling::where('company_id', $companyId)->where('status', 'posted')->latest('billing_date')->get(),
            'invoices' => VendorInvoice::where('company_id', $companyId)->where('match_status', 'matched')->latest('invoice_date')->get(),
            'lines' => BankStatementLine::whereHas('bankAccount', fn ($q) => $q->where('company_id', $companyId))->latest('transaction_date')->limit(100)->get(),
            'periods' => FiscalPeriod::where('company_id', $companyId)->latest('starts_at')->get(),
        ]);
    }

    public function bank(Request $request, CurrentCompany $current)
    {
        $data = $request->validate(['account_id' => ['required', 'exists:accounts,id'], 'code' => ['required', 'max:50'], 'bank_name' => ['required', 'max:120'], 'account_name' => ['required', 'max:120'], 'account_number' => ['required', 'max:80'], 'currency' => ['required', 'size:3']]);
        abort_unless(Account::where('company_id', $current->id())->whereKey($data['account_id'])->exists(), 422);
        BankAccount::create([...$data, 'company_id' => $current->id()]);

        return back()->with('status', 'Rekening bank ditambahkan.');
    }

    public function receipt(Request $request, CurrentCompany $current, CashBankService $service)
    {
        $data = $request->validate(['progress_billing_id' => ['required', 'integer'], 'bank_account_id' => ['required', 'integer'], 'amount' => ['required', 'decimal:0,2', 'gt:0'], 'date' => ['required', 'date'], 'number' => ['required', 'max:80'], 'reference' => ['required', 'max:120'], 'idempotency_key' => ['required', 'max:120']]);
        $service->receiveCustomer(ProgressBilling::where('company_id', $current->id())->findOrFail($data['progress_billing_id']), BankAccount::where('company_id', $current->id())->findOrFail($data['bank_account_id']), $data['amount'], $data['date'], $data['number'], $data['reference'], $data['idempotency_key'], $request->user());

        return back()->with('status', 'Penerimaan pelanggan diposting.');
    }

    public function payment(Request $request, CurrentCompany $current, CashBankService $service)
    {
        $data = $request->validate(['vendor_invoice_id' => ['required', 'integer'], 'bank_account_id' => ['required', 'integer'], 'amount' => ['required', 'decimal:0,2', 'gt:0'], 'date' => ['required', 'date'], 'number' => ['required', 'max:80'], 'reference' => ['required', 'max:120'], 'idempotency_key' => ['required', 'max:120']]);
        $service->payVendor(VendorInvoice::where('company_id', $current->id())->findOrFail($data['vendor_invoice_id']), BankAccount::where('company_id', $current->id())->findOrFail($data['bank_account_id']), $data['amount'], $data['date'], $data['number'], $data['reference'], $data['idempotency_key'], $request->user());

        return back()->with('status', 'Pembayaran vendor diposting.');
    }

    public function statement(Request $request, CurrentCompany $current)
    {
        $data = $request->validate(['bank_account_id' => ['required', 'integer'], 'transaction_date' => ['required', 'date'], 'reference' => ['required', 'max:120'], 'description' => ['nullable', 'max:500'], 'amount' => ['required', 'decimal:0,2', 'not_in:0']]);
        abort_unless(BankAccount::where('company_id', $current->id())->whereKey($data['bank_account_id'])->exists(), 422);
        BankStatementLine::create($data);

        return back()->with('status', 'Baris statement ditambahkan.');
    }

    public function reconcile(Request $request, BankStatementLine $line, CurrentCompany $current, CashBankService $service)
    {
        abort_unless($line->bankAccount()->where('company_id', $current->id())->exists(), 404);
        $data = $request->validate(['transaction_type' => ['required', 'in:customer_receipt,vendor_payment'], 'transaction_id' => ['required', 'integer']]);
        $service->reconcile($line, $data['transaction_type'], $data['transaction_id'], $request->user());

        return back()->with('status', 'Statement berhasil direkonsiliasi.');
    }

    public function close(Request $request, FiscalPeriod $period, CurrentCompany $current, FiscalPeriodClosingService $service)
    {
        abort_unless($period->company_id === $current->id(), 404);
        $service->close($period, $request->user());

        return back()->with('status', 'Periode fiskal ditutup.');
    }
}
