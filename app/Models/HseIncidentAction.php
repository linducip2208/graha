<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HseIncidentAction extends Model
{
    protected $guarded = [];

    public function hseIncident(): BelongsTo
    {
        return $this->belongsTo(HseIncident::class);
    }

    protected function casts(): array
    {
        return ['due_at' => 'date', 'verified_at' => 'datetime'];
    }
}
