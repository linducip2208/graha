<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemHealthState extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_tested_at' => 'datetime'];
    }
}
