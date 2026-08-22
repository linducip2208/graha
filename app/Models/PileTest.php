<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PileTest extends Model
{
    public const TYPES = ['PIT', 'PDA', 'CSL', 'SLT', 'DLT', 'OTHER'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['scheduled_date' => 'date', 'tested_at' => 'date', 'consultant_approved_at' => 'datetime'];
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
