<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldEvidence extends Model
{
    protected $table = 'field_evidences';

    protected $guarded = [];

    public const TYPES = [
        'drilling' => BoredPileDrilling::class,
        'delivery' => ConcreteDelivery::class,
        'test' => PileTest::class,
        'cage' => ReinforcementCage::class,
        'casing' => CasingUnit::class,
        'tool' => Tool::class,
    ];

    /** Label jenis evidence untuk UI. */
    public const LABELS = [
        'drilling' => 'Drilling',
        'delivery' => 'Delivery Beton',
        'test' => 'Pengujian Pile',
        'cage' => 'Cage Tulangan',
        'casing' => 'Casing Pile',
        'tool' => 'Alat Bantu',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
