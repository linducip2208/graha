<?php

namespace App\Models;

use App\Contracts\ApprovalSyncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ContractChange extends Model implements ApprovalSyncable
{
    public const TYPES = [
        'variation_order' => 'Variation Order (VO)',
        'addendum' => 'Addendum',
        'eot' => 'Extension of Time (EOT)',
        'claim' => 'Claim',
        'liquidated_damages' => 'Liquidated Damages (LD)',
        'bond' => 'Bond/Garansi',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'effective_date' => 'date', 'approved_at' => 'datetime', 'metadata' => 'array'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvalRequests(): MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'approvable');
    }

    public function workLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type.' — '.$this->title;
    }

    public function syncApprovalStatus(string $decision): void
    {
        if ($decision === 'approve') {
            $this->forceFill(['status' => 'approved', 'approved_at' => now()])->save();

            return;
        }
        $this->update(['status' => $decision === 'reject' ? 'rejected' : 'draft']);
    }
}
