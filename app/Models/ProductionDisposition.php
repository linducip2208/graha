<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionDisposition extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'cost_amount' => 'decimal:2', 'decided_at' => 'datetime'];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(ProductionInspection::class, 'production_inspection_id');
    }
}
