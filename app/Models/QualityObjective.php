<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityObjective extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['target_value' => 'decimal:2', 'actual_value' => 'decimal:2', 'due_date' => 'date'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
