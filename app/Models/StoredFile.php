<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Metadata file fisik (ADR-048). Database tidak pernah menyimpan binary —
 * hanya path object storage, checksum, dan atribut untuk otorisasi/audit.
 */
class StoredFile extends Model
{
    public const STATUSES = ['uploading', 'processing', 'ready', 'quarantined', 'archived', 'deleted'];

    public const CATEGORIES = ['photo', 'document', 'as_built', 'dossier', 'handover'];

    /** Kategori foto evidence lapangan bored pile (urutan = urutan fase konstruksi). */
    public const PHOTO_CATEGORIES = [
        'setting_out' => 'Setting Out',
        'drilling' => 'Drilling',
        'bore_log' => 'Bore Log',
        'bottom_cleaning' => 'Bottom Cleaning',
        'inspection' => 'Inspection',
        'cage' => 'Cage Tulangan',
        'casing' => 'Casing',
        'tremie' => 'Tremie',
        'concrete' => 'Concrete',
        'slump' => 'Slump Test',
        'testing' => 'Testing',
        'completion' => 'Completion',
        'ncr' => 'NCR',
        'other' => 'Lainnya',
    ];

    public const VARIANT_TYPES = ['original', 'preview', 'thumb'];

    protected $guarded = [];

    /** Bind route dengan UUID (bukan PK) agar tidak enumerable. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'captured_at' => 'datetime',
            'metadata' => 'array',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'size_bytes' => 'integer',
            'storage_locator' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $file) {
            $file->uuid ??= (string) Str::uuid();
            $file->uploaded_at ??= now();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function boredPile(): BelongsTo
    {
        return $this->belongsTo(BoredPile::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function storageProfile(): BelongsTo
    {
        return $this->belongsTo(CompanyStorageProfile::class);
    }

    public function originalFile(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_file_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(self::class, 'original_file_id');
    }

    public function variant(string $type): ?self
    {
        return $this->variants->firstWhere('variant_type', $type);
    }

    /** Nama unduhan: nama asli bila tersedia, else UUID. */
    public function downloadName(): string
    {
        if (filled($this->original_name)) {
            return str_contains($this->original_name, '.') ? $this->original_name : $this->original_name.'.'.($this->extension ?? 'bin');
        }

        return $this->uuid.'.'.($this->extension ?? 'bin');
    }
}
