<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorrectiveAction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_at' => 'date', 'verified_at' => 'datetime'];
    }
}
