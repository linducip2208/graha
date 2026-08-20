<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AuditTrail
{
    public function record(?int $companyId, ?int $actorId, string $event, ?Model $subject = null): AuditLog
    {
        return DB::transaction(function () use ($companyId, $actorId, $event, $subject) {
            $previous = AuditLog::lockForUpdate()->latest('id')->value('entry_hash');
            $created = now();

            return AuditLog::create(['company_id' => $companyId, 'actor_id' => $actorId, 'event' => $event, 'auditable_type' => $subject?->getMorphClass(), 'auditable_id' => $subject?->getKey(), 'previous_hash' => $previous, 'entry_hash' => hash('sha256', json_encode([$companyId, $actorId, $event, $subject?->getKey(), $previous, $created->toISOString()])), 'created_at' => $created]);
        }, 3);
    }
}
