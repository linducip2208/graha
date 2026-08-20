<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ToolboxMeeting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['meeting_date' => 'date', 'attendee_ids' => 'array'];
    }
}
