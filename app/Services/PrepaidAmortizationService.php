<?php

namespace App\Services;

use App\Models\AccountingMapping;
use App\Models\PrepaidExpense;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Prepaid expense / beban dibayar dimuka (ADR-072): posting amortisasi bulanan
 * Dr Beban / Kr Prepaid via mapping `prepaid_amortization` (expense_debit,
 * prepaid_credit). Idempotent per periode; status otomatis completed saat
 * seluruh periode terposting. Bulan terakhir menyerap sisa pembulatan.
 */
class PrepaidAmortizationService
{
    public function __construct(private AccountingService $accounting, private AuditTrail $audit) {}

    public function create(int $companyId, array $data, User $actor): PrepaidExpense
    {
        return DB::transaction(function () use ($companyId, $data, $actor) {
            throw_if(bccomp((string) $data['total_amount'], '0', 2) <= 0, ValidationException::withMessages(['total_amount' => 'Nilai prepaid harus positif.']));
            throw_unless((int) $data['period_count'] >= 1 && (int) $data['period_count'] <= 120, ValidationException::withMessages(['period_count' => 'Periode amortisasi harus 1-120 bulan.']));
            $this->mapping($companyId, 'expense_debit');
            $this->mapping($companyId, 'prepaid_credit');
            $prepaid = PrepaidExpense::create(['company_id' => $companyId, 'name' => $data['name'], 'vendor_ref' => $data['vendor_ref'] ?? null, 'total_amount' => $data['total_amount'], 'period_count' => (int) $data['period_count'], 'first_period_date' => date('Y-m-01', strtotime($data['first_period_date'])), 'status' => 'active', 'notes' => $data['notes'] ?? null, 'created_by' => $actor->id]);
            $this->audit->record($companyId, $actor->id, 'finance.prepaid_created', $prepaid);

            return $prepaid;
        }, 3);
    }

    public function postPeriod(PrepaidExpense $prepaid, string $yearMonth, User $actor): PrepaidExpense
    {
        return DB::transaction(function () use ($prepaid, $yearMonth, $actor) {
            $prepaid = PrepaidExpense::lockForUpdate()->findOrFail($prepaid->id);
            throw_unless($prepaid->status === 'active', ValidationException::withMessages(['status' => 'Prepaid sudah selesai diamortisasi.']));

            $index = $this->periodIndex($prepaid, $yearMonth);
            if ($index === null) {
                throw ValidationException::withMessages(['period' => 'Periode di luar jangka waktu amortisasi atau sudah terposting.']);
            }
            $amount = $this->amountFor($prepaid, $index);
            $journal = $this->accounting->post($prepaid->company_id, Carbon::parse($yearMonth)->endOfMonth()->toDateString(), 'prepaid_amortization', (string) $prepaid->id, 'Amortisasi '.$prepaid->name.' '.$yearMonth, [
                ['account_id' => $this->mapping($prepaid->company_id, 'expense_debit'), 'debit' => $amount, 'credit' => '0'],
                ['account_id' => $this->mapping($prepaid->company_id, 'prepaid_credit'), 'debit' => '0', 'credit' => $amount],
            ], 'prepaid:'.$prepaid->id.':'.$yearMonth, $actor);
            $prepaid->update(['last_posted_period' => Carbon::parse($yearMonth)->startOfMonth()->toDateString()]);
            if ($index === $prepaid->period_count - 1) {
                $prepaid->update(['status' => 'completed']);
            }
            $this->audit->record($prepaid->company_id, $actor->id, 'finance.prepaid_amortized', $journal);

            return $prepaid;
        }, 3);
    }

    /** Posting semua periode jatuh tempo semua prepaid aktif. Return jumlah jurnal. */
    public function postAllDue(User $actor): int
    {
        $posted = 0;
        PrepaidExpense::where('status', 'active')->chunkById(50, function ($prepaids) use (&$posted, $actor): void {
            foreach ($prepaids as $prepaid) {
                foreach ($prepaid->duePeriods() as $period) {
                    try {
                        $this->postPeriod($prepaid->refresh(), substr($period, 0, 7), $actor);
                        $posted++;
                    } catch (\Throwable) {
                        // Periode fiskal tertutup: lewati, coba lagi nanti.
                    }
                }
            }
        });

        return $posted;
    }

    private function periodIndex(PrepaidExpense $prepaid, string $yearMonth): ?int
    {
        $target = Carbon::parse($yearMonth.'-01')->startOfMonth();
        for ($index = 0; $index < $prepaid->period_count; $index++) {
            $cursor = $prepaid->first_period_date->copy()->startOfMonth()->addMonths($index);
            if ($cursor->equalTo($target)) {
                if ($prepaid->last_posted_period !== null && ! $cursor->gt($prepaid->last_posted_period->copy()->startOfMonth())) {
                    return null; // sudah diposting
                }

                return $index;
            }
        }

        return null;
    }

    /** Pembulatan ke bawah per bulan; bulan terakhir menyerap sisa. */
    private function amountFor(PrepaidExpense $prepaid, int $index): string
    {
        if ($index === $prepaid->period_count - 1) {
            $postedSoFar = bcmul($prepaid->monthlyAmount(), (string) ($prepaid->period_count - 1), 2);

            return bcsub((string) $prepaid->total_amount, $postedSoFar, 2);
        }

        return $prepaid->monthlyAmount();
    }

    private function mapping(int $companyId, string $side): int
    {
        $found = AccountingMapping::where('company_id', $companyId)->where('event_type', 'prepaid_amortization')->where('entry_side', $side)->value('account_id');
        throw_unless($found, ValidationException::withMessages(['mapping' => "Mapping prepaid_amortization/{$side} belum tersedia."]));

        return (int) $found;
    }
}
