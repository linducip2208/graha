<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tender extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['owner_estimate' => 'decimal:2', 'bid_value' => 'decimal:2', 'estimated_cost' => 'decimal:2'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function outcome(): HasOne
    {
        return $this->hasOne(TenderOutcome::class);
    }
}
