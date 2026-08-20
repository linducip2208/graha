<?php

namespace App\Services;

use App\Models\Tender;
use App\Models\TenderEstimate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EstimatingService
{
    public function __construct(private AuditTrail $audit) {}

    public function createRevision(Tender $tender, User $actor, array $items, string $reason): TenderEstimate
    {
        return DB::transaction(function () use ($tender, $actor, $items, $reason) {
            $version = ((int) $tender->hasMany(TenderEstimate::class)->lockForUpdate()->max('version')) + 1;
            $estimate = TenderEstimate::create(['tender_id' => $tender->id, 'version' => $version, 'revision_reason' => $reason, 'created_by' => $actor->id]);
            $boq = $rab = $rap = '0';
            foreach ($items as $item) {
                $estimate->items()->create($item);
                $boq = bcadd($boq, bcmul((string) $item['quantity'], (string) $item['boq_unit_price'], 2), 2);
                $rab = bcadd($rab, bcmul((string) $item['quantity'], (string) $item['rab_unit_cost'], 2), 2);
                $rap = bcadd($rap, bcmul((string) $item['quantity'], (string) $item['rap_unit_cost'], 2), 2);
            }$estimate->update(['boq_total' => $boq, 'rab_total' => $rab, 'rap_total' => $rap]);
            $tender->update(['estimated_cost' => $rab]);
            $this->audit->record($tender->company_id, $actor->id, 'estimating.revision_created', $estimate);

            return $estimate->refresh();
        }, 3);
    }
}
