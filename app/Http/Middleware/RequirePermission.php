<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentCompany;
use Closure;

class RequirePermission
{
    public function handle($request, Closure $next, string $permission)
    {
        abort_unless($request->user()?->hasPermission($permission, app(CurrentCompany::class)->id()), 403);

        return $next($request);
    }
}
