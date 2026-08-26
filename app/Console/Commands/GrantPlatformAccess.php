<?php

namespace App\Console\Commands;

use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\Services\PlatformAccessService;
use Illuminate\Console\Command;

class GrantPlatformAccess extends Command
{
    protected $signature = 'platform:grant {email} {permission? : Permission tunggal; kosong untuk platform-admin bundle} {--reason=Initial platform administration}';

    protected $description = 'Grant capability platform-global melalui CLI terotorisasi';

    public function handle(PlatformAccessService $access): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('User tidak ditemukan.');

            return self::FAILURE;
        }
        $permissions = $this->argument('permission') ? [$this->argument('permission')] : PlatformAccessGrant::PERMISSIONS;
        foreach ($permissions as $permission) {
            $access->grant($user, $permission, reason: (string) $this->option('reason'));
        }
        $this->info('Grant platform aktif: '.implode(', ', $permissions));

        return self::SUCCESS;
    }
}
