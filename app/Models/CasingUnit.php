<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CasingUnit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['diameter_mm' => 'decimal:2', 'length_m' => 'decimal:3', 'rental_cost_total' => 'decimal:2'];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CasingMovement::class)->latest('occurred_at');
    }

    public function currentPile(): BelongsTo
    {
        return $this->belongsTo(BoredPile::class, 'current_bored_pile_id');
    }
}
