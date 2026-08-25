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
            'design_easting' => 'decimal:4', 'design_northing' => 'decimal:4',
            'actual_easting' => 'decimal:4', 'actual_northing' => 'decimal:4',
            'design_top_elevation' => 'decimal:3', 'actual_top_elevation' => 'decimal:3',
            'design_cutoff_level' => 'decimal:3', 'actual_cutoff_level' => 'decimal:3',
            'casing_required' => 'boolean',
            'platform_ready_at' => 'datetime', 'concrete_booking_confirmed_at' => 'datetime',
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

    public function rig(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'rig_equipment_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(BoredPileActivity::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(StoredFile::class)->whereNull('original_file_id');
    }

    public function drillings(): HasMany
    {
        return $this->hasMany(BoredPileDrilling::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(ConcreteDelivery::class)->orderBy('arrived_at');
    }

    public function tests(): HasMany
    {
        return $this->hasMany(PileTest::class);
    }

    public function cages(): HasMany
    {
        return $this->hasMany(ReinforcementCage::class, 'bored_pile_id');
    }

    public function cleaningInspections(): HasMany
    {
        return $this->hasMany(PileBottomCleaningInspection::class, 'bored_pile_id')->latest('inspected_at');
    }

    public function readinessChecks(): HasMany
    {
        return $this->hasMany(PileReadinessCheck::class, 'bored_pile_id')->latest('id');
    }

    public function acceptance(): HasOne
    {
        return $this->hasOne(PileAcceptance::class, 'bored_pile_id')->latestOfMany();
    }
}
