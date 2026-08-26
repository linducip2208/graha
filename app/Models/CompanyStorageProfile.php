<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyStorageProfile extends Model
{
    public const DRIVERS = ['local', 's3'];

    public const PRESETS = ['cloudflare-r2', 'aws-s3', 'wasabi', 'minio', 'backblaze-b2', 'digitalocean-spaces', 'custom'];

    public const STATUSES = ['draft', 'connected', 'failed', 'disabled'];

    protected $guarded = [];

    protected $hidden = ['access_key_encrypted', 'secret_key_encrypted'];

    protected function casts(): array
    {
        return [
            'access_key_encrypted' => 'encrypted',
            'secret_key_encrypted' => 'encrypted',
            'use_path_style_endpoint' => 'boolean',
            'is_active' => 'boolean',
            'is_default_evidence' => 'boolean',
            'is_default_document' => 'boolean',
            'last_tested_at' => 'datetime',
            'credentials_updated_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function storedFiles(): HasMany
    {
        return $this->hasMany(StoredFile::class, 'storage_profile_id');
    }

    public function maskedAccessKey(): string
    {
        $value = (string) $this->access_key_encrypted;

        return $value === '' ? 'Belum diatur' : str_repeat('•', 8).mb_substr($value, -4);
    }

    public function endpointHostname(): ?string
    {
        return $this->endpoint ? parse_url($this->endpoint, PHP_URL_HOST) : null;
    }
}
