<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Log tremie per segmen pour; embedment dihitung deterministik oleh service. */
class PileTremieLog extends Model
{
    public const FLAGS = ['normal', 'warning', 'out_of_range'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'tremie_total_length_m' => 'decimal:2',
            'tremie_tip_depth_m' => 'decimal:2',
            'concrete_level_m' => 'decimal:2',
            'embedment_m' => 'decimal:2',
        ];
    }

    public function pile(): BelongsTo
    {
        return $this->belongsTo(BoredPile::class, 'bored_pile_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
