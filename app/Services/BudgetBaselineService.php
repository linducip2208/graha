<?php

namespace App\Services;

use App\Models\BudgetBaseline;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Budget baseline versi (ADR-053): Budget v0/v1/v2... sebagai snapshot
 * immutable. Baseline lama tidak pernah ditimpa; approval memindahkan
 * status, dan hanya satu approved aktif per proyek (yang lain superseded).
 */
class BudgetBaselineService
{
    public function __construct(private AuditTrail $audit) {}

    /** Baris teks "code|name|qty|unit_cost" diparse ketat; amount = qty × unit_cost. */
    public function parseLines(string $raw): array
    {
        $lines = [];
        $total = '0';
        foreach (preg_split('/\r\n|\r|\n/', trim($raw)) ?: [] as $i => $row) {
            $parts = array_map('trim', explode('|', $row));
            throw_unless(count($parts) === 4 && $parts[0] !== '' && $parts[1] !== '' && bccomp($parts[2], '0', 4) === 1 && bccomp($parts[3], '0', 2) >= 0, ValidationException::withMessages(['lines' => 'Baris '.($i + 1).' harus format: kode|uraian|qty|harga_satuan.']));
            $amount = bcmul($parts[2], $parts[3], 2);
            $lines[] = ['code' => mb_substr($parts[0], 0, 40), 'name' => mb_substr($parts[1], 0, 180), 'quantity' => $parts[2], 'unit_cost' => $parts[3], 'amount' => $amount];
            $total = bcadd($total, $amount, 2);
        }
        throw_if($lines === [], ValidationException::withMessages(['lines' => 'Minimal satu baris anggaran.']));

        return ['lines' => $lines, 'total' => $total];
    }

    public function createVersion(Project $project, array $parsed, string $notes, User $actor): BudgetBaseline
    {
        return DB::transaction(function () use ($project, $parsed, $notes, $actor) {
            // Draft lama yang belum disetujui otomatis digantikan draft baru.
            BudgetBaseline::where('project_id', $project->id)->where('status', 'draft')->update(['status' => 'superseded']);
            $version = (int) BudgetBaseline::where('project_id', $project->id)->max('version') + 1;
            $baseline = BudgetBaseline::create([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'version' => $version,
                'status' => 'draft',
                'lines' => $parsed['lines'],
                'total_budget' => $parsed['total'],
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);
            $this->audit->record($project->company_id, $actor->id, 'budget.baseline_created', $baseline);

            return $baseline;
        }, 3);
    }

    public function approve(BudgetBaseline $baseline, User $actor): BudgetBaseline
    {
        return DB::transaction(function () use ($baseline, $actor) {
            $baseline = BudgetBaseline::lockForUpdate()->findOrFail($baseline->id);
            throw_unless($baseline->status === 'draft', ValidationException::withMessages(['status' => 'Hanya draft yang dapat disetujui.']));
            BudgetBaseline::where('project_id', $baseline->project_id)->where('status', 'approved')->update(['status' => 'superseded']);
            $baseline->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->audit->record($baseline->company_id, $actor->id, 'budget.baseline_approved', $baseline);

            return $baseline->refresh();
        }, 3);
    }

    public static function activeApproved(int $projectId): ?BudgetBaseline
    {
        return BudgetBaseline::where('project_id', $projectId)->where('status', 'approved')->orderByDesc('version')->first();
    }
}
