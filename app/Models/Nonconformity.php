<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nonconformity extends Model
{
    protected $guarded = [];

    public function actions(): HasMany
    {
        return $this->hasMany(CorrectiveAction::class);
    }
}
