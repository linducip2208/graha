<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\InspectionTestPlan;
use App\Models\ItpInspection;
use App\Models\ItpItem;
use App\Models\NumberSequence;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Inspection & Test Plan (ADR-059): rencana inspeksi per proyek/pile dengan hold point terpantau. */
class ItpService
{
    public function __construct(private AuditTrail $audit, private NumberSequenceService $numbers) {}

    public function createPlan(Project $project, ?BoredPile $pile, array $data, User $actor): InspectionTestPlan
    {
        return DB::transaction(function () use ($project, $pile, $data, $actor) {
            throw_unless($project->company_id && (int) $project->company_id === (int) ($data['company_id'] ?? 0), ValidationException::withMessages(['project' => 'Proyek tidak valid.']));
            if ($pile) {
                throw_unless((int) $pile->project_id === (int) $project->id, ValidationException::withMessages(['bored_pile_id' => 'Titik pile bukan milik proyek ini.']));
            }
            NumberSequence::firstOrCreate(['company_id' => $project->company_id, 'document_type' => 'itp'], ['prefix' => 'ITP', 'padding' => 4, 'last_reset_year' => now()->year]);
            $plan = InspectionTestPlan::create(['company_id' => $project->company_id, 'project_id' => $project->id, 'bored_pile_id' => $pile?->id, 'number' => $this->numbers->next($project->company_id, 'itp'), 'title' => $data['title'], 'status' => 'active', 'notes' => $data['notes'] ?? null, 'prepared_by' => $actor->id]);
            foreach ($data['items'] as $index => $item) {
                $plan->items()->create(['stage' => $item['stage'], 'method' => $item['method'], 'acceptance_criteria' => $item['acceptance_criteria'], 'checkpoint_type' => $item['checkpoint_type'], 'frequency' => $item['frequency'] ?? null, 'sort_order' => $index]);
            }
            throw_if($plan->items()->count() === 0, ValidationException::withMessages(['items' => 'ITP minimal memiliki satu item inspeksi.']));
            $this->audit->record($project->company_id, $actor->id, 'qms.itp_created', $plan);

            return $plan;
        }, 3);
    }

    public function recordInspection(ItpItem $item, string $performedAt, string $result, ?string $measuredValue, ?string $notes, int $companyId, User $inspector, User $actor): ItpInspection
    {
        return DB::transaction(function () use ($item, $performedAt, $result, $measuredValue, $notes, $companyId, $inspector, $actor) {
            $item = ItpItem::lockForUpdate()->findOrFail($item->id);
            $plan = InspectionTestPlan::lockForUpdate()->findOrFail($item->inspection_test_plan_id);
            throw_unless((int) $plan->company_id === $companyId && $plan->status === 'active', ValidationException::withMessages(['plan' => 'ITP tidak valid atau sudah ditutup.']));
            throw_unless(in_array($result, ['pass', 'fail', 'pending'], true), ValidationException::withMessages(['result' => 'Hasil inspeksi tidak valid.']));
            if ($result === 'fail') {
                throw_unless(filled($notes), ValidationException::withMessages(['notes' => 'Hasil fail wajib mencatat temuan.']));
            }
            throw_if((int) $inspector->id === (int) $actor->id && $result !== 'pending', ValidationException::withMessages(['inspector_id' => 'Pemeriksa harus berbeda dari perekam hasil (verifikasi independen).']));

            $inspection = ItpInspection::create(['itp_item_id' => $item->id, 'performed_at' => $performedAt, 'result' => $result, 'measured_value' => $measuredValue, 'notes' => $notes, 'inspector_id' => $inspector->id, 'created_by' => $actor->id]);
            $this->audit->record($companyId, $actor->id, 'qms.itp_inspection_recorded', $inspection);

            return $inspection;
        }, 3);
    }

    /** Hold point yang belum tertutup inspeksi pass — untuk gate/pantauan. */
    public function openHoldPoints(InspectionTestPlan $plan): array
    {
        return $plan->items->filter(fn (ItpItem $item) => $item->holdOpen())->values()->all();
    }
}
