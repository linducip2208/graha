<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Support\Facades\DB;

class ResolveCompany
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();
        abort_if(! $user || $user->is_active === false, 403);
        $id = $request->header('X-Company-Id') ?: $request->session()->get('company_id');
        $membership = DB::table('company_user')->where('user_id', $user->id)->where('is_active', true)
            ->when($id, fn ($query) => $query->where('company_id', $id))
            ->orderByDesc('is_default')->first();
        $company = $membership ? Company::find($membership->company_id) : null;
        abort_unless($company, 403);
        app(CurrentCompany::class)->set($company);
        $request->session()->put('company_id', $company->id);

        return $next($request);
    }
}
