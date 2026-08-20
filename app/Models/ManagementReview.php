<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManagementReview extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['meeting_date' => 'date', 'inputs_snapshot' => 'array'];
    }
}
