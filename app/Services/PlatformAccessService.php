<?php

namespace App\Services;

use App\Models\PlatformAccessGrant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PlatformAccessService
{
    public function allows(?User $user, string $permission): bool
    {
        return $user !== null && in_array($permission, PlatformAccessGrant::PERMISSIONS, true)
            && PlatformAccessGrant::where('user_id', $user->id)->where('permission', $permission)->whereNull('revoked_at')->exists();
    }

    public function grant(User $user, string $permission, ?User $grantor = null, string $reason = ''): PlatformAccessGrant
    {
        throw_unless(in_array($permission, PlatformAccessGrant::PERMISSIONS, true), ValidationException::withMessages(['permission' => 'Permission platform tidak dikenal.']));

        return PlatformAccessGrant::updateOrCreate(['user_id' => $user->id, 'permission' => $permission], ['granted_by' => $grantor?->id, 'granted_at' => now(), 'revoked_at' => null, 'reason' => $reason]);
    }
}
