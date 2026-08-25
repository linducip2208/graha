<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentDowntimeLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /** Durasi jam; masih berjalan dihitung sampai sekarang. */
    public function hours(): string
    {
        $end = $this->ended_at ?? now();

        return max('0', bcdiv((string) ($this->started_at->diffInMinutes($end, true) ?? 0), '60', 2));
    }
}
