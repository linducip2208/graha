<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerComplaint extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['complaint_date' => 'date', 'resolved_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function ncr(): BelongsTo
    {
        return $this->belongsTo(Nonconformity::class);
    }
}
