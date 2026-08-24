<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class BoredPile extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected static function booted(): void
    {
        // public_uuid = identifier QR publik yang immutable, terpisah dari PK.
        static::creating(function (self $pile) {
            $pile->public_uuid ??= $pile->uuid ?? (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'diameter_mm' => 'decimal:2', 'planned_depth_m' => 'decimal:3', 'actual_depth_m' => 'decimal:3',
            'theoretical_concrete_m3' => 'decimal:4', 'actual_concrete_m3' => 'decimal:4',
            'overbreak_percent' => 'decimal:3', 'overbreak_exceeded' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ProjectZone::class, 'project_zone_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(BoredPileActivity::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(StoredFile::class)->whereNull('original_file_id');
    }

    public function acceptance(): HasOne
    {
        return $this->hasOne(PileAcceptance::class, 'bored_pile_id')->latestOfMany();
    }
}
