<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Acceptance record satu pile — lifecycle: pending → qa_review →
 * engineer_review → accepted|rejected|conditional. Konstruksi selesai ≠
 * diterima: gate acceptance membaca data nyata (uji, NCR, evidence, survey).
 */
class PileAcceptance extends Model
{
    public const STATUSES = ['pending', 'qa_review', 'engineer_review', 'accepted', 'rejected', 'conditional'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'gate_checks' => 'array',
            'requested_at' => 'datetime',
            'qa_reviewed_at' => 'datetime',
            'engineer_reviewed_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $acceptance) {
            $acceptance->uuid ??= (string) Str::uuid();
        });
    }

    public function pile(): BelongsTo
    {
        return $this->belongsTo(BoredPile::class, 'bored_pile_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
