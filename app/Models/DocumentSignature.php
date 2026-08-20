<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSignature extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(SignatureProvider::class, 'signature_provider_id');
    }
}
