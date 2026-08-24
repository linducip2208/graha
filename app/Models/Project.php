<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['planned_start' => 'date', 'planned_end' => 'date', 'closed_at' => 'datetime', 'contract_value' => 'decimal:2', 'estimated_cost' => 'decimal:2', 'overbreak_tolerance_percent' => 'decimal:3'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sourceTender(): BelongsTo
    {
        return $this->belongsTo(Tender::class, 'source_tender_id');
    }

    public function zones(): HasMany
    {
        return $this->hasMany(ProjectZone::class);
    }

    public function boredPiles(): HasMany
    {
        return $this->hasMany(BoredPile::class);
    }
}
