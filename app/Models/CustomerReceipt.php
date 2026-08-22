<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReceipt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['receipt_date' => 'date', 'amount' => 'decimal:2', 'withholding_amount' => 'decimal:2', 'bukti_potong_date' => 'date'];
    }

    public function billing(): BelongsTo
    {
        return $this->belongsTo(ProgressBilling::class, 'progress_billing_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function withholdingTaxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'withholding_tax_rate_id');
    }
}
