<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArWriteOff extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['request_date' => 'date', 'amount' => 'decimal:2', 'decided_at' => 'datetime'];
    }

    public function billing(): BelongsTo
    {
        return $this->belongsTo(ProgressBilling::class, 'progress_billing_id');
    }

    public function finalJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'final_journal_id');
    }
}
