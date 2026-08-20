<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'due_at' => 'datetime', 'completed_at' => 'datetime', 'amount' => 'decimal:2'];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class);
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }
}
