<?php

namespace App\Services;

use App\Models\AccountingMapping;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\CustomerReceipt;
use App\Models\Journal;
use App\Models\ProgressBilling;
use App\Models\User;
use App\Models\VendorInvoice;
use App\Models\VendorPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashBankService
{
    public function __construct(private AccountingService $accounting, private AuditTrail $audit) {}

    public function receiveCustomer(ProgressBilling $billing, BankAccount $bank, string $amount, string $date, string $number, string $reference, string $key, User $actor): CustomerReceipt
    {
        return DB::transaction(function () use ($billing, $bank, $amount, $date, $number, $reference, $key, $actor) {
            if ($old = CustomerReceipt::where('company_id', $billing->company_id)->where('idempotency_key', $key)->first()) {
                return $old;
            }throw_unless($billing->status === 'posted' && $bank->company_id === $billing->company_id, ValidationException::withMessages(['billing' => 'Billing/bank tidak valid.']));
            $paid = (string) CustomerReceipt::where('progress_billing_id', $billing->id)->where('status', 'posted')->sum('amount');
            throw_if(bccomp(bcadd($paid, $amount, 2), (string) $billing->net_receivable, 2) === 1, ValidationException::withMessages(['amount' => 'Penerimaan melebihi outstanding AR.']));
            $ar = $this->mapping($billing->company_id, 'customer_receipt', 'ar_credit');
            $journal = $this->accounting->post($billing->company_id, $date, 'customer_receipt', $number, 'Penerimaan '.$number, [['account_id' => $bank->account_id, 'debit' => $amount, 'credit' => '0', 'project_id' => $billing->project_id], ['account_id' => $ar, 'debit' => '0', 'credit' => $amount, 'project_id' => $billing->project_id]], 'customer-receipt:'.$key, $actor);
            $receipt = CustomerReceipt::create(['company_id' => $billing->company_id, 'progress_billing_id' => $billing->id, 'bank_account_id' => $bank->id, 'number' => $number, 'receipt_date' => $date, 'amount' => $amount, 'reference' => $reference, 'status' => 'posted', 'journal_id' => $journal->id, 'created_by' => $actor->id, 'idempotency_key' => $key]);
            $this->audit->record($billing->company_id, $actor->id, 'finance.customer_receipt', $receipt);

            return $receipt;
        }, 3);
    }

    public function payVendor(VendorInvoice $invoice, BankAccount $bank, string $amount, string $date, string $number, string $reference, string $key, User $actor): VendorPayment
    {
        return DB::transaction(function () use ($invoice, $bank, $amount, $date, $number, $reference, $key, $actor) {
            if ($old = VendorPayment::where('company_id', $invoice->company_id)->where('idempotency_key', $key)->first()) {
                return $old;
            }throw_unless($invoice->match_status === 'matched' && $bank->company_id === $invoice->company_id, ValidationException::withMessages(['invoice' => 'Invoice/bank tidak valid.']));
            throw_unless(Journal::where('company_id', $invoice->company_id)->where('source_type', 'vendor_invoice')->where('source_id', (string) $invoice->id)->where('status', 'posted')->exists(), ValidationException::withMessages(['invoice' => 'Invoice belum diposting ke AP.']));
            $paid = (string) VendorPayment::where('vendor_invoice_id', $invoice->id)->where('status', 'posted')->sum('amount');
            throw_if(bccomp(bcadd($paid, $amount, 2), (string) $invoice->total, 2) === 1, ValidationException::withMessages(['amount' => 'Pembayaran melebihi outstanding AP.']));
            $ap = $this->mapping($invoice->company_id, 'vendor_payment', 'ap_debit');
            $journal = $this->accounting->post($invoice->company_id, $date, 'vendor_payment', $number, 'Pembayaran '.$number, [['account_id' => $ap, 'debit' => $amount, 'credit' => '0'], ['account_id' => $bank->account_id, 'debit' => '0', 'credit' => $amount]], 'vendor-payment:'.$key, $actor);
            $payment = VendorPayment::create(['company_id' => $invoice->company_id, 'vendor_invoice_id' => $invoice->id, 'bank_account_id' => $bank->id, 'number' => $number, 'payment_date' => $date, 'amount' => $amount, 'reference' => $reference, 'status' => 'posted', 'journal_id' => $journal->id, 'created_by' => $actor->id, 'idempotency_key' => $key]);
            $this->audit->record($invoice->company_id, $actor->id, 'finance.vendor_payment', $payment);

            return $payment;
        }, 3);
    }

    public function reconcile(BankStatementLine $line, string $type, int $transactionId, User $actor): BankStatementLine
    {
        return DB::transaction(function () use ($line, $type, $transactionId, $actor) {
            $line = BankStatementLine::lockForUpdate()->findOrFail($line->id);
            throw_unless($line->status === 'unreconciled', ValidationException::withMessages(['line' => 'Statement sudah direkonsiliasi.']));
            $class = match ($type) {
                'customer_receipt' => CustomerReceipt::class,'vendor_payment' => VendorPayment::class,default => throw ValidationException::withMessages(['type' => 'Tipe transaksi tidak valid.'])
            };
            $transaction = $class::findOrFail($transactionId);
            $statementAmount = $type === 'vendor_payment' ? ltrim((string) $line->amount, '-') : (string) $line->amount;
            throw_unless($transaction->bank_account_id === $line->bank_account_id && bccomp((string) $transaction->amount, $statementAmount, 2) === 0, ValidationException::withMessages(['match' => 'Bank, nilai, atau transaksi tidak cocok.']));
            $line->update(['status' => 'reconciled', 'matched_transaction_type' => $class, 'matched_transaction_id' => $transaction->id, 'reconciled_by' => $actor->id, 'reconciled_at' => now()]);
            $this->audit->record($line->bankAccount->company_id, $actor->id, 'finance.bank_reconciled', $line);

            return $line->refresh();
        }, 3);
    }

    private function mapping(int $companyId, string $event, string $side): int
    {
        $mapping = AccountingMapping::where('company_id', $companyId)->where('event_type', $event)->where('entry_side', $side)->first();
        throw_unless($mapping, ValidationException::withMessages(['mapping' => "Mapping $event/$side belum tersedia."]));

        return $mapping->account_id;
    }
}
