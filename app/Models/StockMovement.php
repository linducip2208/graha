<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $guarded = [];

    protected $table = 'stock_movements';

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'balance_after' => 'decimal:4', 'unit_cost' => 'decimal:4', 'posted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Stock ledger immutable.'));
        static::deleting(fn () => throw new \LogicException('Stock ledger tidak boleh dihapus.'));
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
