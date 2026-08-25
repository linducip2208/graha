<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringJournal extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['lines' => 'array', 'next_run_at' => 'date', 'last_posted_at' => 'date'];
    }
}
