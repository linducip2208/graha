<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderParticipant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['bid_value' => 'decimal:2', 'is_winner' => 'boolean'];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }
}
