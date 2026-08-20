<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillOfMaterial extends Model
{
    protected $table = 'bills_of_material';

    protected $guarded = [];

    public function items(): HasMany
    {
        return $this->hasMany(BillOfMaterialItem::class);
    }
}
