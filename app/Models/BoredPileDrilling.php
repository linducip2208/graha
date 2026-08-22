<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoredPileDrilling extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['drilling_started_at' => 'datetime', 'drilling_finished_at' => 'datetime', 'groundwater_level_m' => 'decimal:3', 'sediment_depth_mm' => 'decimal:2', 'verified_at' => 'datetime'];
    }

    public function pile(): BelongsTo
    {
        return $this->belongsTo(BoredPile::class, 'bored_pile_id');
    }

    public function layers(): HasMany
    {
        return $this->hasMany(BoredPileDrillingLayer::class)->orderBy('sequence');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
