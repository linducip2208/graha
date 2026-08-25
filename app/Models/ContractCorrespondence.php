<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractCorrespondence extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['correspondence_date' => 'date'];
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(ProjectAward::class, 'project_award_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }
}
