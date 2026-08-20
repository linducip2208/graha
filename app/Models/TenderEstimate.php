<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenderEstimate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['boq_total' => 'decimal:2', 'rab_total' => 'decimal:2', 'rap_total' => 'decimal:2'];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TenderEstimateItem::class);
    }
}
