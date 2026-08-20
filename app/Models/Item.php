<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Item extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['allow_negative' => 'boolean', 'minimum_stock' => 'decimal:4', 'reorder_point' => 'decimal:4'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
