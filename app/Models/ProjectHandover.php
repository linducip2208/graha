<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectHandover extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(ProjectAward::class, 'project_award_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProjectHandoverItem::class);
    }
}
