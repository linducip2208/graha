<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PpeIssuance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['issued_at' => 'date', 'returned_at' => 'date'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
