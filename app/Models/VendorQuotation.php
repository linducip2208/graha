<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorQuotation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['technical_score' => 'decimal:2', 'commercial_score' => 'decimal:2'];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function totalPrice(): string
    {
        return $this->items->reduce(fn ($carry, $item) => bcadd($carry, bcmul((string) $item->quantity, (string) $item->unit_price, 2), 2), '0');
    }
}
