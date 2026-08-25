<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HseExposureLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['period_month' => 'date', 'man_hours' => 'decimal:2'];
    }
}
