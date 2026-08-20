<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrder extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['planned_quantity' => 'decimal:4', 'completed_quantity' => 'decimal:4', 'actual_material_cost' => 'decimal:2'];
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterial::class, 'bill_of_material_id');
    }

    public function materialIssues(): HasMany
    {
        return $this->hasMany(ProductionMaterialIssue::class);
    }
}
