<?php

namespace App\Services;

use App\Models\ProjectAward;
use App\Models\ProjectHandover;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectAwardService
{
    private const CHECKLIST = ['spk' => 'SPK', 'contract' => 'Kontrak', 'final_offer' => 'Penawaran final', 'boq' => 'BOQ final', 'rab_rap' => 'RAB dan RAP', 'scope' => 'Scope', 'drawing' => 'Drawing', 'schedule' => 'Schedule', 'billing' => 'Billing terms', 'risk' => 'Project risks', 'equipment' => 'Equipment requirement', 'material' => 'Material requirement', 'manpower' => 'Manpower', 'hse' => 'HSE requirement', 'quality' => 'Quality requirement'];

    public function __construct(private AuditTrail $audit) {}

    public function prepareHandover(ProjectAward $award, User $actor): ProjectHandover
    {
        return DB::transaction(function () use ($award, $actor) {
            $handover = ProjectHandover::firstOrCreate(['project_award_id' => $award->id], ['prepared_by' => $actor->id]);
            foreach (self::CHECKLIST as $code => $label) {
                $handover->items()->firstOrCreate(['item_code' => $code], ['label' => $label, 'is_required' => true]);
            }

return $handover;
        }, 3);
    }

    public function activate(ProjectAward $award, User $actor): ProjectAward
    {
        return DB::transaction(function () use ($award, $actor) {
            $award = ProjectAward::with('handover.items')->lockForUpdate()->findOrFail($award->id);
            $missing = [];
            if (! $award->legal_approved) {
                $missing[] = 'legal approval';
            }if (! $award->finance_tax_approved) {
                $missing[] = 'finance & tax approval';
            }if (! $award->signed) {
                $missing[] = 'signature';
            }if (! $award->project_manager_id) {
                $missing[] = 'project manager';
            }if (! $award->handover || $award->handover->items->contains(fn ($i) => $i->is_required && ! $i->is_complete)) {
                $missing[] = 'project handover';
            }throw_if($missing, ValidationException::withMessages(['activation' => 'Belum lengkap: '.implode(', ', $missing)]));
            $award->update(['status' => 'effective']);
            $award->handover->update(['status' => 'completed', 'completed_at' => now()]);
            $this->audit->record($award->company_id, $actor->id, 'project_award.activated', $award);

            return $award;
        }, 3);
    }
}
