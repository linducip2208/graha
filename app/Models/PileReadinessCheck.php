<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot hasil evaluasi readiness engine (immutable history).
 * Snapshot terakhir per kind dipakai untuk menampilkan "terakhir dicek".
 */
class PileReadinessCheck extends Model
{
    public const KIND_DRILL = 'drill';

    public const KIND_CAST = 'cast';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'blockers' => 'array',
            'checklist' => 'array',
        ];
    }

    public function pile(): BelongsTo
    {
        return $this->belongsTo(BoredPile::class, 'bored_pile_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
