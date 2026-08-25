<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractInsurance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'insured_amount' => 'decimal:2', 'premium' => 'decimal:2'];
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(ProjectAward::class, 'project_award_id');
    }

    public function statusNow(): string
    {
        if ($this->end_date->copy()->startOfDay()->isPast()) {
            return 'expired';
        }
        $daysLeft = now()->startOfDay()->diffInDays($this->end_date->copy()->startOfDay());
        if ($daysLeft <= 30) {
            return 'expiring';
        }

        return 'active';
    }
}
