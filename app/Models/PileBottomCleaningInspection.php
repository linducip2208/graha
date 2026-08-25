<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Inspeksi pembersihan dasar lubang (bottom cleaning) sebelum cage diturunkan. */
class PileBottomCleaningInspection extends Model
{
    public const STATUSES = ['pending', 'accepted', 'rejected'];

    public const METHODS = ['airlift', 'grabbing', 'cleaning_bucket', 'circulation', 'other'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cleaned_at' => 'datetime',
            'inspected_at' => 'datetime',
            'sediment_thickness_mm' => 'decimal:2',
        ];
    }

    public function pile(): BelongsTo
    {
        return $this->belongsTo(BoredPile::class, 'bored_pile_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function witness(): BelongsTo
    {
        return $this->belongsTo(User::class, 'witnessed_by');
    }

    public function evidenceFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'evidence_file_id');
    }
}
