<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTransmittalItem extends Model
{
    protected $guarded = [];

    public function transmittal(): BelongsTo
    {
        return $this->belongsTo(DocumentTransmittal::class, 'document_transmittal_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }
}
