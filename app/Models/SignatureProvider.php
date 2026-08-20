<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignatureProvider extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['api_key_encrypted' => 'encrypted', 'webhook_secret_encrypted' => 'encrypted', 'extra_headers' => 'array', 'is_active' => 'boolean'];
    }
}
