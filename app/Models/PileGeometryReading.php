<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pembacaan geometri lubang (caliper/survey/telemetry). Sumber disimpan apa
 * adanya — hasil turunan TIDAK pernah dilabeli certified survey.
 */
class PileGeometryReading extends Model
{
    public const SOURCES = ['manual', 'survey', 'caliper_import', 'telemetry'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'depth_m' => 'decimal:3',
            'measured_diameter_mm' => 'decimal:2',
            'deviation_x_mm' => 'decimal:2',
            'deviation_y_mm' => 'decimal:2',
            'verticality_percent' => 'decimal:3',
        ];
    }

    public function pile(): BelongsTo
    {
        return $this->belongsTo(BoredPile::class, 'bored_pile_id');
    }
}
