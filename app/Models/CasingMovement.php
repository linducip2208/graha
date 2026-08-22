<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CasingMovement extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'cost' => 'decimal:2'];
    }

    public function casingUnit(): BelongsTo
    {
        return $this->belongsTo(CasingUnit::class, 'casing_unit_id');
    }

    public function pile(): BelongsTo
    {
        return $this->belongsTo(BoredPile::class, 'bored_pile_id');
    }
}
