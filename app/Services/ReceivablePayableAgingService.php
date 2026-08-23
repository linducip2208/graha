<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\ProgressBilling;
use App\Models\VendorInvoice;
use Illuminate\Support\Carbon;

class ReceivablePayableAgingService
{
    public function generate(int $companyId, Carbon $asOf): array
    {
        $receivables = ProgressBilling::where('company_id', $companyId)->where('status', 'posted')->whereDate('billing_date', '<=', $asOf)->with(['project.customer'])->get()->map(function (ProgressBilling $billing) use ($asOf): array {
            $receipts = $billing->customerReceipts()->where('status', 'posted')->whereDate('receipt_date', '<=', $asOf);
            $paid = bcadd((string) $receipts->sum('amount'), (string) $receipts->sum('withholding_amount'), 2);
            $outstanding = bcsub((string) $billing->net_receivable, $paid, 2);

            return ['type' => 'AR', 'number' => $billing->number, 'party' => $billing->project?->customer?->name ?? 'Customer', 'date' => $billing->billing_date, 'due_date' => $billing->due_date ?? $billing->billing_date->copy()->addDays($billing->project?->customer?->payment_term_days ?? 30), 'amount' => (string) $billing->net_receivable, 'paid' => $paid, 'outstanding' => $outstanding];
        })->filter(fn (array $row) => bccomp($row['outstanding'], '0', 2) === 1)->values();
        $payables = VendorInvoice::where('company_id', $companyId)->where('match_status', 'matched')->whereDate('invoice_date', '<=', $asOf)->with('vendor')->get()->map(function (VendorInvoice $invoice) use ($asOf, $companyId): array {
            $payments = $invoice->vendorPayments()->where('status', 'posted')->whereDate('payment_date', '<=', $asOf);
            $paid = bcadd((string) $payments->sum('amount'), (string) $payments->sum('withholding_amount'), 2);
            $outstanding = bcsub((string) $invoice->total, $paid, 2);

            return ['type' => 'AP', 'number' => $invoice->number, 'party' => $invoice->vendor?->name ?? 'Vendor', 'date' => $invoice->invoice_date, 'due_date' => $invoice->invoice_date->copy()->addDays((int) CompanySetting::val($companyId, 'default_vendor_payment_term_days')), 'amount' => (string) $invoice->total, 'paid' => $paid, 'outstanding' => $outstanding];
        })->filter(fn (array $row) => bccomp($row['outstanding'], '0', 2) === 1)->values();

        $rows = $receivables->concat($payables)->map(function (array $row) use ($asOf): array {
            $days = max(0, $row['due_date']->diffInDays($asOf, false));
            $row['bucket'] = $days <= 30 ? '0–30 hari' : ($days <= 60 ? '31–60 hari' : ($days <= 90 ? '61–90 hari' : '>90 hari'));
            $row['days_overdue'] = $days;

            return $row;
        });

        return ['rows' => $rows, 'receivables' => $receivables, 'payables' => $payables, 'ar_total' => $this->sum($receivables), 'ap_total' => $this->sum($payables), 'buckets' => $rows->groupBy('bucket')->map(fn ($group) => $this->sum($group))];
    }

    private function sum($rows): string
    {
        return $rows->reduce(fn (string $carry, array $row) => bcadd($carry, $row['outstanding'], 2), '0');
    }
}
