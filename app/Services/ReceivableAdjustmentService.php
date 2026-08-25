<?php

namespace App\Services;

use App\Models\AccountingMapping;
use App\Models\ArCreditNote;
use App\Models\ArWriteOff;
use App\Models\CustomerReceipt;
use App\Models\NumberSequence;
use App\Models\ProgressBilling;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Penyesuaian piutang: credit note (potong tagihan) dan write-off (hapus buku tak tertagih) — ADR-057. */
class ReceivableAdjustmentService
{
    public function __construct(private AccountingService $accounting, private AuditTrail $audit, private NumberSequenceService $numbers) {}

    public function outstanding(ProgressBilling $billing): string
    {
        $receipts = CustomerReceipt::where('progress_billing_id', $billing->id)->where('status', 'posted');
        $paid = bcadd((string) $receipts->sum('amount'), (string) $receipts->sum('withholding_amount'), 2);
        $credited = (string) ArCreditNote::where('progress_billing_id', $billing->id)->where('status', 'posted')->sum('amount');
        $writtenOff = (string) ArWriteOff::where('progress_billing_id', $billing->id)->where('status', 'approved')->sum('amount');

        return max('0', bcsub(bcsub(bcsub((string) $billing->net_receivable, $paid, 2), $credited, 2), $writtenOff, 2));
    }

    public function creditNote(ProgressBilling $billing, string $amount, string $reason, string $date, string $key, User $actor): ArCreditNote
    {
        return DB::transaction(function () use ($billing, $amount, $reason, $date, $key, $actor) {
            if ($old = ArCreditNote::where('company_id', $billing->company_id)->where('idempotency_key', $key)->first()) {
                return $old;
            }
            throw_unless($billing->company_id && $billing->status === 'posted', ValidationException::withMessages(['billing' => 'Billing harus berstatus posted.']));
            throw_if(bccomp($amount, '0', 2) <= 0, ValidationException::withMessages(['amount' => 'Nominal harus positif.']));
            throw_if(bccomp($amount, $this->outstanding($billing), 2) === 1, ValidationException::withMessages(['amount' => 'Credit note melebihi sisa piutang billing ini.']));

            $revenueDebit = $this->mapping($billing->company_id, 'ar_credit_note', 'revenue_debit');
            $arCredit = $this->mapping($billing->company_id, 'ar_credit_note', 'ar_credit');
            $number = $this->nextNumber($billing->company_id, 'ar_credit_note', 'CN');
            $journal = $this->accounting->post($billing->company_id, $date, 'ar_credit_note', $number, 'Credit Note '.$number.' - '.$billing->number, [
                ['account_id' => $revenueDebit, 'debit' => $amount, 'credit' => '0', 'project_id' => $billing->project_id],
                ['account_id' => $arCredit, 'debit' => '0', 'credit' => $amount, 'project_id' => $billing->project_id],
            ], 'ar-credit-note:'.$key, $actor);
            $note = ArCreditNote::create(['company_id' => $billing->company_id, 'progress_billing_id' => $billing->id, 'number' => $number, 'note_date' => $date, 'amount' => $amount, 'reason' => $reason, 'status' => 'posted', 'journal_id' => $journal->id, 'created_by' => $actor->id, 'idempotency_key' => $key]);
            $this->audit->record($billing->company_id, $actor->id, 'finance.ar_credit_note_posted', $note);

            return $note;
        }, 3);
    }

    public function requestWriteOff(ProgressBilling $billing, string $amount, string $reason, string $date, string $key, User $actor): ArWriteOff
    {
        return DB::transaction(function () use ($billing, $amount, $reason, $date, $key, $actor) {
            if ($old = ArWriteOff::where('company_id', $billing->company_id)->where('idempotency_key', $key)->first()) {
                return $old;
            }
            throw_unless($billing->status === 'posted', ValidationException::withMessages(['billing' => 'Billing harus berstatus posted.']));
            throw_if(bccomp($amount, '0', 2) <= 0, ValidationException::withMessages(['amount' => 'Nominal harus positif.']));
            throw_if(bccomp($amount, $this->outstanding($billing), 2) === 1, ValidationException::withMessages(['amount' => 'Write-off melebihi sisa piutang billing ini.']));

            $writeOff = ArWriteOff::create(['company_id' => $billing->company_id, 'progress_billing_id' => $billing->id, 'number' => $this->nextNumber($billing->company_id, 'ar_write_off', 'WO'), 'request_date' => $date, 'amount' => $amount, 'reason' => $reason, 'status' => 'pending_approval', 'requested_by' => $actor->id, 'idempotency_key' => $key]);
            $this->audit->record($billing->company_id, $actor->id, 'finance.ar_write_off_requested', $writeOff);

            return $writeOff;
        }, 3);
    }

    public function approveWriteOff(ArWriteOff $writeOff, string $date, User $approver): ArWriteOff
    {
        return DB::transaction(function () use ($writeOff, $date, $approver) {
            $writeOff = ArWriteOff::lockForUpdate()->findOrFail($writeOff->id);
            throw_unless($writeOff->status === 'pending_approval', ValidationException::withMessages(['status' => 'Pengajuan sudah diputuskan.']));
            throw_unless($writeOff->company_id && ProgressBilling::whereKey($writeOff->progress_billing_id)->where('company_id', $writeOff->company_id)->exists(), ValidationException::withMessages(['billing' => 'Billing tidak valid.']));
            throw_if((int) $writeOff->requested_by === $approver->id, ValidationException::withMessages(['approver' => 'Pengaju tidak dapat menyetujui write-off sendiri (self-approval dilarang).']));
            throw_if(bccomp($writeOff->amount, $this->outstanding($writeOff->billing), 2) === 1, ValidationException::withMessages(['amount' => 'Sisa piutang tidak mencukupi (ada penyesuaian lain).']));

            $expenseDebit = $this->mapping($writeOff->company_id, 'ar_write_off', 'expense_debit');
            $arCredit = $this->mapping($writeOff->company_id, 'ar_write_off', 'ar_credit');
            $billing = $writeOff->billing;
            $journal = $this->accounting->post($writeOff->company_id, $date, 'ar_write_off', $writeOff->number, 'Write-off '.$writeOff->number.' - '.$billing->number, [
                ['account_id' => $expenseDebit, 'debit' => $writeOff->amount, 'credit' => '0', 'project_id' => $billing->project_id],
                ['account_id' => $arCredit, 'debit' => '0', 'credit' => $writeOff->amount, 'project_id' => $billing->project_id],
            ], 'ar-write-off:'.$writeOff->idempotency_key, $approver);
            $writeOff->update(['status' => 'approved', 'decided_by' => $approver->id, 'decided_at' => now(), 'final_journal_id' => $journal->id]);
            $this->audit->record($writeOff->company_id, $approver->id, 'finance.ar_write_off_approved', $writeOff);

            return $writeOff;
        }, 3);
    }

    public function rejectWriteOff(ArWriteOff $writeOff, string $notes, User $approver): ArWriteOff
    {
        return DB::transaction(function () use ($writeOff, $notes, $approver) {
            $writeOff = ArWriteOff::lockForUpdate()->findOrFail($writeOff->id);
            throw_unless($writeOff->status === 'pending_approval', ValidationException::withMessages(['status' => 'Pengajuan sudah diputuskan.']));
            throw_if((int) $writeOff->requested_by === $approver->id, ValidationException::withMessages(['approver' => 'Pengaju tidak dapat menolak pengajuannya sendiri.']));
            $writeOff->update(['status' => 'rejected', 'decided_by' => $approver->id, 'decided_at' => now(), 'decision_notes' => $notes]);
            $this->audit->record($writeOff->company_id, $approver->id, 'finance.ar_write_off_rejected', $writeOff);

            return $writeOff;
        }, 3);
    }

    private function mapping(int $companyId, string $event, string $side): int
    {
        $found = AccountingMapping::where('company_id', $companyId)->where('event_type', $event)->where('entry_side', $side)->value('account_id');
        throw_unless($found, ValidationException::withMessages(['mapping' => "Mapping $event/$side belum tersedia."]));

        return (int) $found;
    }

    private function nextNumber(int $companyId, string $type, string $prefix): string
    {
        NumberSequence::firstOrCreate(['company_id' => $companyId, 'document_type' => $type], ['prefix' => $prefix, 'padding' => 4, 'last_reset_year' => now()->year]);

        return $this->numbers->next($companyId, $type);
    }
}
