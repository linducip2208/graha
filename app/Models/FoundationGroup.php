<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Grup pondasi: pile cap / zona / grup kustom untuk readiness agregat. */
class FoundationGroup extends Model
{
    public const TYPES = ['pile_cap', 'zone', 'custom_group'];

    protected $guarded = [];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Pile anggota grup, urut sequence lalu nomor pile. */
    public function piles(): BelongsToMany
    {
        return $this->belongsToMany(BoredPile::class, 'foundation_group_piles')
            ->withPivot('sequence')
            ->orderBy('foundation_group_piles.sequence')
            ->orderBy('pile_number');
    }
}
