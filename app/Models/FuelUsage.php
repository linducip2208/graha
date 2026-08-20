<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelUsage extends Model
{
    protected $guarded = [];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    protected function casts(): array
    {
        return ['liters' => 'decimal:4', 'liters_per_hour' => 'decimal:4', 'is_anomaly' => 'boolean', 'used_at' => 'datetime'];
    }
}
