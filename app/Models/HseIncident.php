<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HseIncident extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function actions(): HasMany
    {
        return $this->hasMany(HseIncidentAction::class);
    }
}
