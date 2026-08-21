<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoutingOperation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['standard_minutes_per_unit' => 'decimal:4'];
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterial::class, 'bill_of_material_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProductionOperationLog::class);
    }
}
