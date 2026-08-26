<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu interval pencatatan volume beton saat casting. */
class PileConcretePourInterval extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'depth_or_level_m' => 'decimal:3',
            'incremental_volume_m3' => 'decimal:4',
            'cumulative_volume_m3' => 'decimal:4',
        ];
    }

    public function pile(): BelongsTo
    {
        return $this->belongsTo(BoredPile::class, 'bored_pile_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(ConcreteDelivery::class, 'concrete_delivery_id');
    }

    public function tremieLog(): BelongsTo
    {
        return $this->belongsTo(PileTremieLog::class, 'pile_tremie_log_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
