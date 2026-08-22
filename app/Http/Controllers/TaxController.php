<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\CustomerReceipt;
use App\Models\Journal;
use App\Models\ProgressBilling;
use App\Models\TaxRate;
use App\Models\VendorInvoice;
use App\Models\VendorPayment;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaxController extends Controller
{
    public function index(Request $request, CurrentCompany $current)
    {
        $companyId = $current->id();
        $year = (int) ($request->query('year', now()->year));

        $ppnOut = ProgressBilling::where('company_id', $companyId)->where('status', 'posted')
            ->whereYear('billing_date', $year)
            ->selectRaw('MONTH(billing_date) as month, SUM(gross_amount) as dpp, SUM(tax_amount) as tax')
            ->groupBy(DB::raw('MONTH(billing_date)'))->orderBy(DB::raw('MONTH(billing_date)'))->get();
        $ppnIn = VendorInvoice::where('company_id', $companyId)->where('match_status', 'matched')
            ->whereYear('invoice_date', $year)
            ->whereIn('id', Journal::where('company_id', $companyId)->where('source_type', 'vendor_invoice')->where('status', 'posted')->select('source_id'))
            ->selectRaw('MONTH(invoice_date) as month, SUM(subtotal) as dpp, SUM(tax_amount) as tax')
            ->groupBy(DB::raw('MONTH(invoice_date)'))->orderBy(DB::raw('MONTH(invoice_date)'))->get();
        $withheldFromUs = CustomerReceipt::where('company_id', $companyId)->where('status', 'posted')
            ->whereYear('receipt_date', $year)->where('withholding_amount', '>', 0)
            ->selectRaw('MONTH(receipt_date) as month, SUM(withholding_amount) as tax, COUNT(*) as docs')
            ->groupBy(DB::raw('MONTH(receipt_date)'))->orderBy(DB::raw('MONTH(receipt_date)'))->get();
        $withheldToVendors = VendorPayment::where('company_id', $companyId)->where('status', 'posted')
            ->whereYear('payment_date', $year)->where('withholding_amount', '>', 0)
            ->selectRaw('MONTH(payment_date) as month, SUM(withholding_amount) as tax, COUNT(*) as docs')
            ->groupBy(DB::raw('MONTH(payment_date)'))->orderBy(DB::raw('MONTH(payment_date)'))->get();

        $months = [];
        foreach (range(1, 12) as $m) {
            $months[$m] = ['dpp_out' => '0', 'tax_out' => '0', 'dpp_in' => '0', 'tax_in' => '0', 'pph_received' => '0', 'pph_paid' => '0'];
        }
        foreach ($ppnOut as $row) {
            $months[$row->month]['dpp_out'] = bcadd($months[$row->month]['dpp_out'], (string) $row->dpp, 2);
            $months[$row->month]['tax_out'] = bcadd($months[$row->month]['tax_out'], (string) $row->tax, 2);
        }
        foreach ($ppnIn as $row) {
            $months[$row->month]['dpp_in'] = bcadd($months[$row->month]['dpp_in'], (string) $row->dpp, 2);
            $months[$row->month]['tax_in'] = bcadd($months[$row->month]['tax_in'], (string) $row->tax, 2);
        }
        foreach ($withheldFromUs as $row) {
            $months[$row->month]['pph_received'] = bcadd($months[$row->month]['pph_received'], (string) $row->tax, 2);
        }
        foreach ($withheldToVendors as $row) {
            $months[$row->month]['pph_paid'] = bcadd($months[$row->month]['pph_paid'], (string) $row->tax, 2);
        }

        $sum = fn (string $key) => collect($months)->reduce(fn (string $carry, array $month) => bcadd($carry, (string) $month[$key], 2), '0');

        return view('taxes.index', [
            'year' => $year,
            'years' => ProgressBilling::where('company_id', $companyId)->selectRaw('DISTINCT YEAR(billing_date) as y')->pluck('y')->merge([$year])->unique()->sortDesc()->values(),
            'months' => $months,
            'totals' => ['dpp_out' => $sum('dpp_out'), 'tax_out' => $sum('tax_out'), 'dpp_in' => $sum('dpp_in'), 'tax_in' => $sum('tax_in'), 'pph_received' => $sum('pph_received'), 'pph_paid' => $sum('pph_paid')],
            'rates' => TaxRate::where('company_id', $companyId)->orderBy('kind')->orderBy('code')->get(),
            'mappings' => AccountingMapping::where('company_id', $companyId)->whereIn('event_type', ['progress_billing', 'vendor_invoice', 'customer_receipt', 'vendor_payment'])->with('account')->get()->keyBy(fn ($m) => $m->event_type.'.'.$m->entry_side),
            'accounts' => Account::where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(),
            'certificatesOut' => CustomerReceipt::where('company_id', $companyId)->whereNotNull('bukti_potong_number')->with(['billing.project', 'withholdingTaxRate'])->latest('receipt_date')->limit(25)->get(),
            'certificatesIn' => VendorPayment::where('company_id', $companyId)->whereNotNull('bukti_potong_number')->with(['invoice.vendor', 'withholdingTaxRate'])->latest('payment_date')->limit(25)->get(),
        ]);
    }

    public function storeRate(Request $request, CurrentCompany $current)
    {
        $data = $request->validate([
            'code' => ['required', 'max:50', 'unique:tax_rates,code,NULL,id,company_id,'.$current->id()],
            'name' => ['required', 'max:120'],
            'kind' => ['required', 'in:'.implode(',', TaxRate::KINDS)],
            'rate_percent' => ['required', 'decimal:0,4', 'between:0,100'],
            'description' => ['nullable', 'max:500'],
        ]);
        TaxRate::create([...$data, 'company_id' => $current->id()]);

        return back()->with('status', 'Tarif pajak disimpan.');
    }

    public function toggleRate(TaxRate $rate, CurrentCompany $current)
    {
        abort_unless($rate->company_id === $current->id(), 404);
        $rate->update(['is_active' => ! $rate->is_active]);

        return back()->with('status', $rate->refresh()->is_active ? 'Tarif diaktifkan.' : 'Tarif dinonaktifkan.');
    }
}
