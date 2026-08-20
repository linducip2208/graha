<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FiscalPeriod extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date', 'closed_at' => 'datetime'];
    }
}
