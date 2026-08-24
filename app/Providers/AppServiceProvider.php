<?php

namespace App\Providers;

use App\Models\ExperienceVersion;
use App\Models\UserFavorite;
use App\Services\ThemeService;
use App\Support\Experience\ThemePresets;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentCompany::class, fn () => new CurrentCompany);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Experience Platform (ADR-058): resolve token theme untuk company aktif
        // dan bagikan ke shell. Tanpa baris company_experience → default preset.
        View::composer(['components.layouts.app', 'auth.login'], function ($view) {
            $companyId = session('company_id');
            $previewId = session('experience_preview_version');
            $service = app(ThemeService::class);

            // Shell V2: data sidebar/topbar (binding awal agar preview branch pun tetap dapat).
            if (auth()->check() && $companyId) {
                $user = auth()->user();
                $view->with('shellFavorites', UserFavorite::where('user_id', $user->id)->orderBy('sort')->orderBy('id')->limit(5)->get());
                $view->with('shellRole', (string) (DB::table('company_user_role')
                    ->join('company_user', 'company_user.id', '=', 'company_user_role.company_user_id')
                    ->join('roles', 'roles.id', '=', 'company_user_role.role_id')
                    ->where('company_user.user_id', $user->id)
                    ->where('company_user.company_id', (int) $companyId)
                    ->orderBy('roles.id')
                    ->value('roles.name') ?? ''));
                $view->with('shellMemberships', $user->companies()->where('company_user.is_active', true)->orderBy('name')->get(['companies.id', 'companies.name', 'companies.code']));
            } else {
                $view->with('shellFavorites', collect());
                $view->with('shellRole', '');
                $view->with('shellMemberships', collect());
            }

            if ($previewId && auth()->user()?->hasPermission('finance.manage', (int) $companyId)) {
                $version = ExperienceVersion::find($previewId);
                if ($version && (int) $version->company_id === (int) $companyId) {
                    $experience = $service->preview((int) $companyId, $version);
                    $experience['config']['previewing_version'] = $version->version;
                    $view->with('experience', $experience);

                    return;
                }
            }
            $experience = $companyId
                ? app(ThemeService::class)->resolve((int) $companyId)
                : ['tokens' => ThemePresets::get(ThemeService::DEFAULT_PRESET)['tokens'], 'config' => ['preset' => ThemeService::DEFAULT_PRESET]];

            $view->with('experience', $experience);
        });
    }
}
