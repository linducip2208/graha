<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReinforcementCage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['diameter_mm' => 'decimal:2', 'total_length_m' => 'decimal:3', 'theoretical_weight_kg' => 'decimal:2', 'actual_weight_kg' => 'decimal:2', 'qc_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function pile(): BelongsTo
    {
        return $this->belongsTo(BoredPile::class, 'bored_pile_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Selisih berat aktual vs teoretis dalam persen; null bila belum ditimbang. */
    public function weightVariancePercent(): ?float
    {
        if ($this->actual_weight_kg === null || ! $this->theoretical_weight_kg || bccomp((string) $this->theoretical_weight_kg, '0', 2) === 0) {
            return null;
        }

        return round((float) bcdiv(bcmul(bcsub((string) $this->actual_weight_kg, (string) $this->theoretical_weight_kg, 2), '100', 4), (string) $this->theoretical_weight_kg, 4), 2);
    }
}
