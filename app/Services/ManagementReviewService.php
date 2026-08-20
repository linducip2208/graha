<?php

namespace App\Services;

use App\Models\CorrectiveAction;
use App\Models\HseIncident;
use App\Models\InternalAudit;
use App\Models\ManagementReview;
use App\Models\Nonconformity;
use App\Models\RiskOpportunity;
use App\Models\User;

class ManagementReviewService
{
    public function createSnapshot(int $companyId, string $number, string $date, User $chair): ManagementReview
    {
        return ManagementReview::create(['company_id' => $companyId, 'number' => $number, 'meeting_date' => $date, 'chairperson_id' => $chair->id, 'inputs_snapshot' => ['open_risks' => RiskOpportunity::where('company_id', $companyId)->where('status', 'open')->count(), 'open_ncr' => Nonconformity::where('company_id', $companyId)->where('status', '!=', 'closed')->count(), 'overdue_capa' => CorrectiveAction::whereHas('nonconformity', fn ($q) => $q->where('company_id', $companyId))->where('status', '!=', 'effective')->whereDate('due_at', '<', today())->count(), 'planned_audits' => InternalAudit::where('company_id', $companyId)->where('status', 'planned')->count(), 'open_incidents' => HseIncident::where('company_id', $companyId)->where('status', '!=', 'closed')->count()]]);
    }
}
