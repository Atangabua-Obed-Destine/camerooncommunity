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
        $this->assertEnvironmentLoaded();

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

    /**
     * Fail loudly when the request is running on config defaults.
     *
     * Without a config cache, .env is parsed on every single request, and that
     * read can fail — on Windows a concurrent request or an editor holding the
     * file is enough. Laravel's loader is deliberately forgiving about an
     * unreadable .env, so the request simply carries on with every default in
     * place: the database becomes 'laravel', tenancy cannot resolve, and the
     * user gets a 500 whose message names a database nobody configured. It is
     * intermittent, so it lands on whichever request is unlucky — an answered
     * call, a sent message — and looks like a bug in that feature.
     *
     * The cure is `php artisan config:cache`, which stops .env being read per
     * request at all. This only makes the cause legible if it happens anyway.
     */
    private function assertEnvironmentLoaded(): void
    {
        if ($this->app->runningUnitTests() || $this->app->configurationIsCached()) {
            return;
        }

        // config/database.php falls back to 'laravel' when DB_DATABASE is
        // absent, so seeing the fallback while a .env exists means the file
        // was there and did not get read.
        $onDefaults = config('database.connections.mysql.database') === 'laravel'
            && is_file($this->app->environmentFilePath());

        if (! $onDefaults) {
            return;
        }

        throw new \RuntimeException(
            'The .env file exists but was not read for this request, so the app is '
            . 'running on config defaults (database "laravel"). This is usually a '
            . 'transient file-read failure. Run `php artisan config:cache` to stop '
            . '.env being parsed on every request.'
        );
    }
}
