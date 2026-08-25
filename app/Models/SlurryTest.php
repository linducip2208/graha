<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uji slurry terstruktur (ADR-074). Fitur opsional — tanpa kebijakan limit
 * di settings, data hanya terekam (record only) dan tidak jadi gate.
 */
class SlurryTest extends Model
{
    public const PHASES = ['before_drilling', 'during_drilling', 'before_cage', 'before_casting'];

    public const TYPES = ['bentonite', 'polymer', 'water', 'other'];

    public const STATUSES = ['pending', 'accepted', 'rejected'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tested_at' => 'datetime',
            'verified_at' => 'datetime',
            'density' => 'decimal:3',
            'viscosity' => 'decimal:2',
            'ph' => 'decimal:2',
            'sand_content_percent' => 'decimal:2',
            'temperature' => 'decimal:2',
        ];
    }

    public function pile(): BelongsTo
    {
        return $this->belongsTo(BoredPile::class, 'bored_pile_id');
    }

    public function sampler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sampled_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
