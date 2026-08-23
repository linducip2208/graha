<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'locale', 'is_active', 'last_login_at', 'phone', 'avatar_path', 'status', 'invited_at', 'password_changed_at', 'preferences'])]
#[Hidden(['password', 'remember_token', 'mfa_secret', 'mfa_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const STATUSES = [
        'invited'   => 'Diundang',
        'active'    => 'Aktif',
        'suspended' => 'Ditangguhkan',
        'inactive'  => 'Nonaktif',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'invited_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'mfa_enabled_at' => 'datetime',
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'encrypted:array',
            'preferences' => 'collection',
        ];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withPivot(['id', 'branch_id', 'department_id', 'is_default', 'is_active', 'data_scope', 'scope_branch_id', 'scope_department_id'])->withTimestamps();
    }

    public function loginHistories(): HasMany
    {
        return $this->hasMany(UserLoginHistory::class);
    }

    /**
     * Permission check dengan memoization per-request + cache store.
     * Satu query per user+company per 5 menit; invalidated oleh
     * PermissionInvalidator pada mutasi role/membership/permission.
     */
    public function hasPermission(string $code, int $companyId): bool
    {
        return in_array($code, $this->permissionCodes($companyId), true);
    }

    /** Semua kode permission efektif user di sebuah perusahaan (cached). */
    public function permissionCodes(int $companyId): array
    {
        static $memo = [];

        if (! isset($memo[$companyId])) {
            $memo[$companyId] = cache()->remember(
                "perm:{$this->id}:{$companyId}",
                now()->addMinutes(5),
                fn () => Permission::query()
                    ->select('permissions.code')
                    ->join('permission_role', 'permission_role.permission_id', '=', 'permissions.id')
                    ->whereIn('permission_role.role_id', function ($q) use ($companyId) {
                        $q->select('company_user_role.role_id')
                            ->from('company_user_role')
                            ->join('company_user', 'company_user.id', '=', 'company_user_role.company_user_id')
                            ->where('company_user.company_id', $companyId)
                            ->where('company_user.user_id', $this->id)
                            ->where('company_user.is_active', true);
                    })
                    ->distinct()
                    ->pluck('code')
                    ->all()
            );
        }

        return $memo[$companyId];
    }

    // ── Lifecycle ────────────────────────────────────────────────────────

    public function canLogin(): bool
    {
        return $this->is_active && in_array($this->status, ['active'], true);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'emerald',
            'invited' => 'sky',
            'suspended' => 'amber',
            'inactive' => 'slate',
            default => 'slate',
        };
    }

    public function preference(string $key, mixed $default = null): mixed
    {
        return data_get($this->preferences, $key, $default);
    }

    public function setPreference(string $key, mixed $value): void
    {
        $prefs = $this->preferences ?? collect();
        $prefs->put($key, $value);
        $this->forceFill(['preferences' => $prefs->all()])->save();
    }

    public function hasMfaEnabled(): bool
    {
        return $this->mfa_enabled_at !== null && $this->mfa_secret !== null;
    }
}
