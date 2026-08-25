<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractMilestone extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['planned_date' => 'date', 'actual_date' => 'date', 'weight_percent' => 'decimal:3', 'amount' => 'decimal:2'];
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(ProjectAward::class, 'project_award_id');
    }

    public function isLate(): bool
    {
        return $this->status === 'pending' && $this->planned_date !== null && $this->planned_date->isPast();
    }
}
