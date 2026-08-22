<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuelTank extends Model
{
    public const TYPES = ['opening', 'receipt', 'issue_to_equipment', 'issue_other', 'reading_adjustment'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['capacity_l' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FuelTankTransaction::class);
    }
}
