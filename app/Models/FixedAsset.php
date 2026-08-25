<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAsset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['acquisition_date' => 'date', 'depreciation_start_date' => 'date', 'disposed_at' => 'date', 'acquisition_cost' => 'decimal:2', 'residual_value' => 'decimal:2', 'disposal_proceeds' => 'decimal:2'];
    }

    public function disposalJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'disposal_journal_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FixedAssetCategory::class, 'fixed_asset_category_id');
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(AssetDepreciation::class);
    }
}
