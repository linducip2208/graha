<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalibrationRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['calibrated_at' => 'date', 'next_due_at' => 'date'];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /** overdue = lewat jatuh tempo; due_soon = <= 30 hari; ok selainnya. */
    public function statusNow(): string
    {
        $days = now()->startOfDay()->diffInDays($this->next_due_at, false);

        return match (true) {
            $days < 0 => 'overdue',
            $days <= 30 => 'due_soon',
            default => 'ok',
        };
    }
}
