<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (self $balance): void {
            $balance->dimension_key = implode('|', [
                $balance->company_id,
                $balance->item_id,
                $balance->warehouse_id,
                $balance->warehouse_bin_id ?? '0',
                $balance->lot_number ?? '',
            ]);
        });
    }

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'reserved_quantity' => 'decimal:4', 'damaged_quantity' => 'decimal:4', 'obsolete_quantity' => 'decimal:4', 'in_transit_quantity' => 'decimal:4'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'warehouse_bin_id');
    }
}
