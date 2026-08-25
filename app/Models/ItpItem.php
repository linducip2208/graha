<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItpItem extends Model
{
    protected $guarded = [];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InspectionTestPlan::class, 'inspection_test_plan_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(ItpInspection::class)->latest('performed_at');
    }

    /** Hold point tanpa inspeksi pass dianggap belum tertutup. */
    public function holdOpen(): bool
    {
        if ($this->checkpoint_type !== 'hold') {
            return false;
        }

        return ! $this->inspections()->where('result', 'pass')->exists();
    }
}
