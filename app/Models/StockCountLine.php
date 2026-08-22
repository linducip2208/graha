<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['system_quantity' => 'decimal:4', 'counted_quantity' => 'decimal:4'];
    }

    public function count(): BelongsTo
    {
        return $this->belongsTo(StockCount::class, 'stock_count_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function variance(): string
    {
        return bcsub((string) $this->counted_quantity, (string) $this->system_quantity, 4);
    }
}
