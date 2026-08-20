<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoredPile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'diameter_mm' => 'decimal:2', 'planned_depth_m' => 'decimal:3', 'actual_depth_m' => 'decimal:3',
            'theoretical_concrete_m3' => 'decimal:4', 'actual_concrete_m3' => 'decimal:4',
            'overbreak_percent' => 'decimal:3', 'overbreak_exceeded' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ProjectZone::class, 'project_zone_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(BoredPileActivity::class);
    }
}
