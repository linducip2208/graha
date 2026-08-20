<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkPermit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['valid_from' => 'datetime', 'valid_until' => 'datetime', 'closed_at' => 'datetime'];
    }
}
