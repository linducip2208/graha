<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Project;
use App\Models\RecurringJournal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Jurnal berulang (ADR-066): template baris jurnal tetap yang diposting otomatis
 * tiap bulan pada tanggal terjadwal. Idempotency key = recurring:{id}:{Y-m} sehingga
 * eksekusi ganda aman; saldo baris divalidasi saat create dan saat posting.
 */
class RecurringJournalService
{
    public function __construct(private AccountingService $accounting, private AuditTrail $audit) {}

    public function create(int $companyId, array $data, User $actor): RecurringJournal
    {
        return DB::transaction(function () use ($companyId, $data, $actor) {
            throw_unless((int) $data['day_of_month'] >= 1 && (int) $data['day_of_month'] <= 28, ValidationException::withMessages(['day_of_month' => 'Tanggal posting harus 1-28 (aman untuk semua bulan).']));
            $lines = $this->validateLines($companyId, $data['lines']);
            $journal = RecurringJournal::create(['company_id' => $companyId, 'name' => $data['name'], 'description' => $data['description'], 'lines' => $lines, 'day_of_month' => (int) $data['day_of_month'], 'next_run_at' => $this->firstRunDate((int) $data['day_of_month']), 'status' => 'active', 'created_by' => $actor->id]);
            $this->audit->record($companyId, $actor->id, 'finance.recurring_journal_created', $journal);

            return $journal;
        }, 3);
    }

    /** Posting semua template jatuh tempo (semua company). Return jumlah jurnal diposting. */
    public function postDue(?User $systemActor = null): int
    {
        $posted = 0;
        RecurringJournal::where('status', 'active')->where('next_run_at', '<=', now()->endOfDay()->toDateTimeString())->chunkById(50, function ($journals) use (&$posted, $systemActor): void {
            foreach ($journals as $journal) {
                $period = $journal->next_run_at->format('Y-m');
                $date = $journal->next_run_at->toDateString();
                $key = 'recurring:'.$journal->id.':'.$period;
                if (DB::table('journals')->where('company_id', $journal->company_id)->where('idempotency_key', $key)->exists()) {
                    $this->advance($journal);

                    continue;
                }
                try {
                    $this->accounting->post($journal->company_id, $date, 'recurring_journal', (string) $journal->id, $journal->description, $this->preparedLines($journal), $key, $systemActor ?? User::find($journal->created_by));
                    $journal->update(['last_posted_at' => $date]);
                    $this->advance($journal);
                    $posted++;
                } catch (\Throwable) {
                    // Periode fiskal tertutup / akun nonaktif: coba lagi bulan depan.
                    $this->advance($journal, true);
                }
            }
        });

        return $posted;
    }

    public function runNow(RecurringJournal $journal, User $actor): RecurringJournal
    {
        return DB::transaction(function () use ($journal, $actor) {
            $journal = RecurringJournal::lockForUpdate()->findOrFail($journal->id);
            throw_unless($journal->status === 'active', ValidationException::withMessages(['status' => 'Template paused tidak dapat dijalankan.']));
            $today = now()->toDateString();
            $key = 'recurring:'.$journal->id.':'.now()->format('Y-m');
            $this->accounting->post($journal->company_id, $today, 'recurring_journal', (string) $journal->id, $journal->description, $this->preparedLines($journal), $key, $actor);
            $journal->update(['last_posted_at' => $today, 'next_run_at' => now()->addMonthNoOverflow()->setDay($journal->day_of_month)->toDateString()]);
            $this->audit->record($journal->company_id, $actor->id, 'finance.recurring_journal_posted', $journal);

            return $journal;
        }, 3);
    }

    private function advance(RecurringJournal $journal, bool $skipOnly = false): void
    {
        $next = Carbon::parse($journal->next_run_at)->addMonthNoOverflow()->setDay($journal->day_of_month)->toDateString();
        $journal->update(['next_run_at' => $next]);
        unset($skipOnly);
    }

    private function firstRunDate(int $day): string
    {
        $now = now();

        return ($now->day > $day ? $now->addMonthNoOverflow() : $now)->setDay($day)->toDateString();
    }

    private function validateLines(int $companyId, mixed $raw): array
    {
        throw_unless(is_array($raw) && count($raw) >= 2, ValidationException::withMessages(['lines' => 'Minimal dua baris jurnal.']));
        $debit = $credit = '0';
        foreach ($raw as $line) {
            throw_unless(Account::where('company_id', $companyId)->whereKey($line['account_id'] ?? 0)->where('is_active', true)->exists(), ValidationException::withMessages(['lines' => 'Akun tidak valid atau tidak aktif.']));
            if (! empty($line['project_id'])) {
                throw_unless(Project::where('company_id', $companyId)->whereKey($line['project_id'])->exists(), ValidationException::withMessages(['lines' => 'Proyek tidak valid.']));
            }
            $d = (string) ($line['debit'] ?? '0');
            $c = (string) ($line['credit'] ?? '0');
            throw_if(bccomp($d, '0', 2) === 1 && bccomp($c, '0', 2) === 1, ValidationException::withMessages(['lines' => 'Baris hanya boleh debit atau kredit.']));
            $debit = bcadd($debit, $d, 2);
            $credit = bcadd($credit, $c, 2);
        }
        throw_unless(bccomp($debit, $credit, 2) === 0 && bccomp($debit, '0', 2) === 1, ValidationException::withMessages(['lines' => 'Total debit harus sama dengan kredit.']));

        return $raw;
    }

    private function preparedLines(RecurringJournal $journal): array
    {
        return collect($journal->lines)->map(fn (array $line) => ['account_id' => (int) $line['account_id'], 'debit' => (string) ($line['debit'] ?? '0'), 'credit' => (string) ($line['credit'] ?? '0')] + (! empty($line['project_id']) ? ['project_id' => (int) $line['project_id']] : []))->values()->all();
    }
}
