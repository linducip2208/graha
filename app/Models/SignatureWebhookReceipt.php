<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignatureWebhookReceipt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['provider_timestamp' => 'datetime', 'processed_at' => 'datetime'];
    }
}
