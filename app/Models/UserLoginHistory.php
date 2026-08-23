<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLoginHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'event', 'ip_address', 'user_agent', 'company_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function record(int $userId, string $event, ?int $companyId = null): void
    {
        self::create([
            'user_id' => $userId,
            'event' => $event,
            'ip_address' => request()?->ip(),
            'user_agent' => (string) request()?->userAgent(),
            'company_id' => $companyId,
            'created_at' => now(),
        ]);
    }
}
