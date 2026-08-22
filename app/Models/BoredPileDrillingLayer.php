<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoredPileDrillingLayer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['depth_from_m' => 'decimal:3', 'depth_to_m' => 'decimal:3'];
    }

    public function drilling(): BelongsTo
    {
        return $this->belongsTo(BoredPileDrilling::class, 'bored_pile_drilling_id');
    }
}
