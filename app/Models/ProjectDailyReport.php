<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectDailyReport extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['report_date' => 'date'];
    }
}
