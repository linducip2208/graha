<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementPlan extends Model
{
    public const STATUSES = ['planned', 'pr_created', 'po_created', 'received', 'cancelled'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'estimated_value' => 'decimal:2', 'required_date' => 'date', 'planned_pr_date' => 'date', 'planned_po_date' => 'date'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function wbs(): BelongsTo
    {
        return $this->belongsTo(ProjectWbs::class, 'project_wbs_id');
    }
}
