<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDepreciation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['depreciation_date' => 'date', 'amount' => 'decimal:2', 'posted_at' => 'datetime'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }
}
