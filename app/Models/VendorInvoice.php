<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorInvoice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'total' => 'decimal:2', 'match_details' => 'array'];
    }
}
