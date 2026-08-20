<?php

namespace App\Services;

use App\Models\AccountingMapping;
use App\Models\ApprovalRequest;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\RetentionRelease;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RetentionReleaseService
{
    public function __construct(private AccountingService $accounting, private AuditTrail $audit) {}

    public function create(Project $project, array $data, User $actor): RetentionRelease
    {
        return DB::transaction(function () use ($project, $data, $actor) {
            $project = Project::lockForUpdate()->findOrFail($project->id);
            if ($existing = RetentionRelease::where('company_id', $project->company_id)->where('idempotency_key', $data['idempotency_key'])->first()) {
                return $existing;
            }

            $earned = (string) ProgressBilling::where('project_id', $project->id)->where('status', 'posted')->sum('retention_amount');
            $released = (string) RetentionRelease::where('project_id', $project->id)->whereNotIn('status', ['rejected', 'cancelled'])->sum('amount');
            throw_if(bccomp((string) $data['amount'], '0', 2) <= 0 || bccomp(bcadd($released, (string) $data['amount'], 2), $earned, 2) === 1, ValidationException::withMessages(['amount' => 'Release melebihi saldo retensi tersedia.']));

            $release = RetentionRelease::create([...$data, 'company_id' => $project->company_id, 'project_id' => $project->id, 'created_by' => $actor->id]);
            $this->audit->record($project->company_id, $actor->id, 'billing.retention_created', $release);

            return $release;
        }, 3);
    }

    public function activateApproved(RetentionRelease $release, User $actor): RetentionRelease
    {
        return DB::transaction(function () use ($release, $actor) {
            $release = RetentionRelease::lockForUpdate()->findOrFail($release->id);
            $approved = ApprovalRequest::where('approvable_type', RetentionRelease::class)->where('approvable_id', $release->id)->where('status', 'approved')->exists();
            throw_unless($approved, ValidationException::withMessages(['approval' => 'Release retensi belum disetujui.']));
            $release->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);

            return $release->refresh();
        }, 3);
    }

    public function post(RetentionRelease $release, User $actor): RetentionRelease
    {
        return DB::transaction(function () use ($release, $actor) {
            $release = RetentionRelease::lockForUpdate()->findOrFail($release->id);
            if ($release->status === 'posted' && $release->journal_id) {
                return $release;
            }
            throw_unless($release->status === 'approved', ValidationException::withMessages(['status' => 'Release retensi belum approved.']));
            $maps = AccountingMapping::where('company_id', $release->company_id)->where('event_type', 'retention_release')->get()->keyBy('entry_side');
            foreach (['ar_debit', 'retention_credit'] as $side) {
                throw_unless($maps->has($side), ValidationException::withMessages(['mapping' => "Mapping $side belum tersedia."]));
            }
            $journal = $this->accounting->post($release->company_id, $release->release_date->toDateString(), 'retention_release', (string) $release->id, 'Release retensi '.$release->number, [
                ['account_id' => $maps['ar_debit']->account_id, 'debit' => $release->amount, 'credit' => '0', 'project_id' => $release->project_id],
                ['account_id' => $maps['retention_credit']->account_id, 'debit' => '0', 'credit' => $release->amount, 'project_id' => $release->project_id],
            ], 'retention-release:'.$release->id, $actor);
            $release->update(['status' => 'posted', 'journal_id' => $journal->id]);
            $this->audit->record($release->company_id, $actor->id, 'billing.retention_posted', $release);

            return $release->refresh();
        }, 3);
    }
}
