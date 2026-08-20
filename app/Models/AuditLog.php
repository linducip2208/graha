<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit log append-only.'));
        static::deleting(fn () => throw new \LogicException('Audit log tidak boleh dihapus.'));
    }
}
