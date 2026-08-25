<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItpInspection extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['performed_at' => 'date'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItpItem::class, 'itp_item_id');
    }
}
