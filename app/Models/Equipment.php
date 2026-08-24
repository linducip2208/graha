<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    protected $guarded = [];

    protected $table = 'equipment';

    protected function casts(): array
    {
        return ['current_hour_meter' => 'decimal:2', 'fuel_target_lph' => 'decimal:4'];
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function meterLogs(): HasMany
    {
        return $this->hasMany(EquipmentMeterLog::class);
    }

    public function fuelUsages(): HasMany
    {
        return $this->hasMany(FuelUsage::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(MaintenanceWorkOrder::class);
    }
}
