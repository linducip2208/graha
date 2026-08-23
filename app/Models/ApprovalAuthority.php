<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalAuthority extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
            'requires_mfa' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Authority aktif pada tanggal referensi & mencakup nominal. */
    public function covers(float $amount, \DateTimeInterface $date = new \DateTimeImmutable): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->effective_from && $this->effective_from->isFuture()) {
            return false;
        }
        if ($this->effective_until && $this->effective_until->isPast()) {
            return false;
        }

        return $amount >= (float) $this->min_amount && $amount <= (float) $this->max_amount;
    }

    public function getSubjectLabelAttribute(): string
    {
        return $this->user?->name ?? ('Role: '.$this->role?->name);
    }
}
