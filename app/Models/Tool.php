<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tool extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['purchase_cost' => 'decimal:2', 'checked_out_at' => 'datetime', 'expected_return_at' => 'datetime'];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ToolMovement::class)->latest('occurred_at');
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_to');
    }
}
