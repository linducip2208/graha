<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProjectAward extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['award_date' => 'date', 'contract_value' => 'decimal:2', 'retention_percent' => 'decimal:4', 'legal_approved' => 'boolean', 'finance_tax_approved' => 'boolean', 'signed' => 'boolean'];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function handover(): HasOne
    {
        return $this->hasOne(ProjectHandover::class);
    }
}
