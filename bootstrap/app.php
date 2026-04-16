<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\InitializeTenancy::class);
        $middleware->append(\App\Http\Middleware\SetUserLanguage::class);

        $middleware->appendToGroup('web', [
            \App\Http\Middleware\EnsureUserActive::class,
        ]);

        $middleware->alias([
            'tenant' => \App\Http\Middleware\InitializeTenancy::class,
            'locale' => \App\Http\Middleware\SetUserLanguage::class,
            'active' => \App\Http\Middleware\EnsureUserActive::class,
            'location' => \App\Http\Middleware\UpdateUserLocation::class,
            'onboarded' => \App\Http\Middleware\EnsureOnboarded::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
