<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExperienceVersion extends Model
{
    public const STATUSES = ['draft', 'published', 'archived'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['config' => 'array', 'published_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
