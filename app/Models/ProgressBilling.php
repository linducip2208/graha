<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgressBilling extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['billing_date' => 'date', 'due_date' => 'date', 'approved_at' => 'datetime', 'progress_percent' => 'decimal:4', 'gross_amount' => 'decimal:2', 'retention_percent' => 'decimal:4', 'retention_amount' => 'decimal:2', 'advance_recovery' => 'decimal:2', 'tax_amount' => 'decimal:2', 'net_receivable' => 'decimal:2'];
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function customerReceipts(): HasMany
    {
        return $this->hasMany(CustomerReceipt::class);
    }
}
