<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QmsComplianceMapping extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['evidence_expires_at' => 'date', 'next_review_at' => 'date'];
    }
}
