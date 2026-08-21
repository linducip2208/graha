<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkCenter extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['labor_rate_per_hour' => 'decimal:2', 'overhead_rate_per_hour' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function routingOperations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class);
    }
}
