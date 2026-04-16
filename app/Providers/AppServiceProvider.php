<?php

namespace App\Providers;

use App\Services\SiteSettings;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Default null so app('currentTenant') never throws before middleware runs
        $this->app->bind('currentTenant', fn () => null);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', function ($view) {
            static $shared = null;
            if ($shared === null) {
                $shared = [
                    '__siteName' => SiteSettings::name(),
                    '__siteLogo' => SiteSettings::logoUrl(),
                    '__siteFavicon' => SiteSettings::faviconUrl(),
                ];
            }
            $view->with($shared);
        });
    }
}
