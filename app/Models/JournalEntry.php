<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntry extends Model
{
    protected $guarded = [];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $entry) {
            if ($entry->journal()->where('status', 'posted')->exists()) {
                throw new \LogicException('Posted journal immutable.');
            }
        });
        static::deleting(function (self $entry) {
            if ($entry->journal()->where('status', 'posted')->exists()) {
                throw new \LogicException('Posted journal immutable.');
            }
        });
    }
}
