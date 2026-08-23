<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstraintLog extends Model
{
    public const TYPES = ['drawing', 'material', 'equipment', 'manpower', 'permit', 'client', 'weather', 'subcontractor', 'technical'];

    public const STATUSES = ['open', 'in_progress', 'resolved'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['raised_at' => 'date', 'target_date' => 'date', 'resolved_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
