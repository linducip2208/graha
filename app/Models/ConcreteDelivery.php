<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConcreteDelivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['batch_time' => 'datetime', 'arrived_at' => 'datetime', 'pour_started_at' => 'datetime', 'pour_finished_at' => 'datetime', 'ordered_volume_m3' => 'decimal:4', 'delivered_volume_m3' => 'decimal:4', 'accepted_volume_m3' => 'decimal:4', 'rejected_volume_m3' => 'decimal:4', 'slump_cm' => 'decimal:2'];
    }

    public function pile(): BelongsTo
    {
        return $this->belongsTo(BoredPile::class, 'bored_pile_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
