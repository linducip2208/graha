<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectCostForecast extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['forecast_date' => 'date', 'cost_to_complete' => 'decimal:2'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
