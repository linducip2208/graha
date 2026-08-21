<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOperationLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity_processed' => 'decimal:4', 'actual_hours' => 'decimal:4', 'labor_cost' => 'decimal:2', 'overhead_cost' => 'decimal:2', 'performed_at' => 'datetime'];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function routingOperation(): BelongsTo
    {
        return $this->belongsTo(RoutingOperation::class);
    }
}
