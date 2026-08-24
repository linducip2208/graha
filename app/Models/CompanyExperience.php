<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyExperience extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'published_at' => 'datetime', 'nav_config' => 'array', 'terminology' => 'array', 'dashboard_config' => 'array', 'launcher_config' => 'array', 'launcher_covers' => 'array', 'public_site' => 'array'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
