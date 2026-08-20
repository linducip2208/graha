<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    protected $table = 'equipment';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['current_hour_meter' => 'decimal:2', 'fuel_target_lph' => 'decimal:4'];
    }
}
