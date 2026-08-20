<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobSafetyAnalysis extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['hazards' => 'array', 'controls' => 'array', 'valid_from' => 'date', 'valid_until' => 'date'];
    }
}
