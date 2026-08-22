<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldEvidence extends Model
{
    protected $guarded = [];

    public const TYPES = [
        'drilling' => BoredPileDrilling::class,
        'delivery' => ConcreteDelivery::class,
        'test' => PileTest::class,
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
