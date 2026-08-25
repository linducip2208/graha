<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTransmittal extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['transmit_date' => 'date', 'acknowledged_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(DocumentTransmittalItem::class);
    }
}
