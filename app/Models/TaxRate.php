<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRate extends Model
{
    public const KINDS = ['ppn_output', 'ppn_input', 'withholding'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['rate_percent' => 'decimal:4', 'is_active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
