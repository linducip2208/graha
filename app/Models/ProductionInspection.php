<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionInspection extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['inspected_quantity' => 'decimal:4', 'inspected_at' => 'datetime'];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function dispositions(): HasMany
    {
        return $this->hasMany(ProductionDisposition::class);
    }
}
