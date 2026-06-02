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
        // Replace the default 419 "Page Expired" screen with a friendly
        // redirect to the login page so the user knows what happened.
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            $message = __('Your session has expired. Please sign in again.');

            if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
                return response()->json([
                    'message'  => $message,
                    'redirect' => route('login', ['expired' => 1]),
                ], 419);
            }

            return redirect()
                ->guest(route('login', ['expired' => 1]))
                ->with('status', $message);
        });
    })->create();
