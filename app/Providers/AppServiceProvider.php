<?php

namespace App\Providers;

use App\Services\ThemeService;
use App\Support\Experience\ThemePresets;
use App\Support\Tenancy\CurrentCompany;
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
            $experience = $companyId
                ? app(ThemeService::class)->resolve((int) $companyId)
                : ['tokens' => ThemePresets::get(ThemeService::DEFAULT_PRESET)['tokens'], 'config' => ['preset' => ThemeService::DEFAULT_PRESET]];

            $view->with('experience', $experience);
        });
    }
}
