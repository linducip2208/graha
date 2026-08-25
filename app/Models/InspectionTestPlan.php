<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionTestPlan extends Model
{
    protected $guarded = [];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function boredPile(): BelongsTo
    {
        return $this->belongsTo(BoredPile::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItpItem::class)->orderBy('sort_order');
    }
}
