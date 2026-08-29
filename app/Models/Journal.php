<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Journal extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['journal_date' => 'date', 'posted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $journal): void {
            if ($journal->getOriginal('status') === 'posted') {
                throw new \LogicException('Posted journal immutable.');
            }
        });
        static::deleting(function (self $journal): void {
            if ($journal->status === 'posted') {
                throw new \LogicException('Posted journal immutable.');
            }
        });
    }

    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }
}
