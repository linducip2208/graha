<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\Journal;
use App\Models\Project;
use App\Models\ProjectCostLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingService
{
    public function __construct(private NumberSequenceService $numbers, private AuditTrail $audit) {}

    public function post(int $companyId, string $date, string $sourceType, string $sourceId, string $description, array $lines, string $key, User $actor): Journal
    {
        return DB::transaction(function () use ($companyId, $date, $sourceType, $sourceId, $description, $lines, $key, $actor) {
            $fingerprint = hash('sha256', json_encode(['date' => $date, 'source_type' => $sourceType, 'source_id' => $sourceId, 'lines' => $lines], JSON_THROW_ON_ERROR));
            if ($existing = Journal::where('company_id', $companyId)->where('idempotency_key', $key)->first()) {
                throw_if($existing->payload_fingerprint && ! hash_equals($existing->payload_fingerprint, $fingerprint), ValidationException::withMessages(['idempotency_key' => 'Kunci jurnal sudah dipakai untuk payload berbeda.']));

                return $existing;
            }$period = FiscalPeriod::where('company_id', $companyId)->where('starts_at', '<=', $date)->where('ends_at', '>=', $date)->lockForUpdate()->first();
            throw_unless($period && $period->status === 'open', ValidationException::withMessages(['period' => 'Periode fiskal tidak tersedia atau sudah ditutup.']));
            $debit = $credit = '0';
            foreach ($lines as $line) {
                $d = (string) ($line['debit'] ?? '0');
                $c = (string) ($line['credit'] ?? '0');
                throw_if(bccomp($d, '0', 2) === -1 || bccomp($c, '0', 2) === -1, ValidationException::withMessages(['lines' => 'Debit dan kredit tidak boleh negatif.']));
                throw_if((bccomp($d, '0', 2) === 1 && bccomp($c, '0', 2) === 1) || (bccomp($d, '0', 2) === 0 && bccomp($c, '0', 2) === 0), ValidationException::withMessages(['lines' => 'Setiap baris harus memiliki debit atau kredit, bukan keduanya.']));
                $debit = bcadd($debit, $d, 2);
                $credit = bcadd($credit, $c, 2);
                $account = Account::where('company_id', $companyId)->whereKey($line['account_id'])->where('is_active', true)->where('is_postable', true)->exists();
                throw_unless($account, ValidationException::withMessages(['account' => 'Akun tidak valid atau tidak postable.']));
                if (! empty($line['project_id'])) {
                    throw_unless(Project::where('company_id', $companyId)->whereKey($line['project_id'])->exists(), ValidationException::withMessages(['project' => 'Dimensi proyek tidak valid untuk perusahaan ini.']));
                }
            }throw_unless(bccomp($debit, $credit, 2) === 0 && bccomp($debit, '0', 2) === 1, ValidationException::withMessages(['balance' => 'Total debit dan kredit harus seimbang.']));
            $journal = Journal::create(['company_id' => $companyId, 'fiscal_period_id' => $period->id, 'number' => $this->numbers->next($companyId, 'journal'), 'journal_date' => $date, 'source_type' => $sourceType, 'source_id' => $sourceId, 'description' => $description, 'status' => 'draft', 'idempotency_key' => $key, 'payload_fingerprint' => $fingerprint, 'created_by' => $actor->id]);
            foreach ($lines as $line) {
                $entry = $journal->entries()->create($line);
                if (! empty($line['project_id']) && bccomp((string) ($line['debit'] ?? '0'), '0', 2) === 1) {
                    ProjectCostLedger::create(['company_id' => $companyId, 'project_id' => $line['project_id'], 'project_cost_code_id' => $line['project_cost_code_id'] ?? null, 'journal_entry_id' => $entry->id, 'cost_type' => $line['cost_type'] ?? 'actual', 'amount' => $line['debit'], 'cost_date' => $date]);
                }
            }$journal->update(['status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now()]);
            $this->audit->record($companyId, $actor->id, 'accounting.journal_posted', $journal);

            return $journal->load('entries');
        }, 3);
    }
}
