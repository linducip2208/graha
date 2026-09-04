<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\PlatformAccessService;
use App\Support\Tenancy\CurrentCompany;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'locale', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
        ];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withPivot(['id', 'branch_id', 'department_id', 'is_default', 'is_active'])->withTimestamps();
    }

    public function hasPermission(string $code, ?int $companyId = null): bool
    {
        // AdminLTE's Gate integration asks for a single-argument permission
        // check. Resolve that call against the active tenant while preserving
        // the explicit company ID used by Graha's controllers and middleware.
        $companyId ??= (int) session('company_id', 0);

        if ($companyId < 1 && app()->bound(CurrentCompany::class)) {
            try {
                $companyId = app(CurrentCompany::class)->id();
            } catch (\Throwable) {
                $companyId = 0;
            }
        }

        if ($companyId < 1) {
            return false;
        }

        return Role::query()->whereHas('permissions', fn ($q) => $q->where('code', $code))->whereExists(fn ($q) => $q->selectRaw('1')->from('company_user_role')->join('company_user', 'company_user.id', '=', 'company_user_role.company_user_id')->whereColumn('company_user_role.role_id', 'roles.id')->where('company_user.company_id', $companyId)->where('company_user.user_id', $this->id)->where('company_user.is_active', true))->exists();
    }

    public function platformAccessGrants(): HasMany
    {
        return $this->hasMany(PlatformAccessGrant::class);
    }

    public function hasPlatformPermission(string $code): bool
    {
        return app(PlatformAccessService::class)->allows($this, $code);
    }
}
