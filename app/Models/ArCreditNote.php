<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArCreditNote extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['note_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function billing(): BelongsTo
    {
        return $this->belongsTo(ProgressBilling::class, 'progress_billing_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
