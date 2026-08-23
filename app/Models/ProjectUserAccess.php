<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectUserAccess extends Model
{
    public const LEVELS = [
        'view' => 'View',
        'contributor' => 'Contributor',
        'manager' => 'Manager',
    ];

    protected $table = 'project_user_access';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Assignment aktif pada tanggal referensi. */
    public function isActiveAt(\DateTimeInterface $date = new \DateTimeImmutable): bool
    {
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }
}
