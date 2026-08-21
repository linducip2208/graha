<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillOfMaterial extends Model
{
    protected $table = 'bills_of_material';

    protected $guarded = [];

    public function items(): HasMany
    {
        return $this->hasMany(BillOfMaterialItem::class);
    }

    public function outputItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'output_item_id');
    }

    public function routingOperations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class)->orderBy('sequence');
    }
}
