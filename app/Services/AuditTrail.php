<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AuditTrail
{
    public function record(?int $companyId, ?int $actorId, string $event, ?Model $subject = null, array $metadata = []): AuditLog
    {
        return DB::transaction(function () use ($companyId, $actorId, $event, $subject, $metadata) {
            $previous = AuditLog::lockForUpdate()->latest('id')->value('entry_hash');
            $created = now();

            return AuditLog::create(['company_id' => $companyId, 'actor_id' => $actorId, 'event' => $event, 'auditable_type' => $subject?->getMorphClass(), 'auditable_id' => $subject?->getKey(), 'new_values' => $metadata ?: null, 'previous_hash' => $previous, 'entry_hash' => hash('sha256', json_encode([$companyId, $actorId, $event, $subject?->getKey(), $metadata, $previous, $created->toISOString()])), 'created_at' => $created]);
        }, 3);
    }
}
