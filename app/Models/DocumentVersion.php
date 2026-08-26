<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_signed' => 'boolean', 'locked_at' => 'datetime', 'storage_locator' => 'array'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function storageProfile(): BelongsTo
    {
        return $this->belongsTo(CompanyStorageProfile::class);
    }

    protected static function booted(): void
    {
        static::updating(function (self $version) {
            if ($version->getOriginal('locked_at') || $version->getOriginal('is_signed')) {
                throw new \LogicException('Versi dokumen terkunci tidak boleh diubah.');
            }
        });
        static::deleting(fn (self $version) => $version->locked_at || $version->is_signed ? false : null);
    }
}
