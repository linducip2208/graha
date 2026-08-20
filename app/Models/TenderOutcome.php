<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderOutcome extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['announced_at' => 'date', 'additional_reasons' => 'array', 'winning_bid_value' => 'decimal:2', 'negotiated_value' => 'decimal:2', 'contract_value' => 'decimal:2'];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }
}
