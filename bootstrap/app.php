<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // The mobile app's surface. Served under /api, versioned inside, and
        // deliberately separate from web.php: these routes carry no session
        // and answer only in JSON.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Must run before anything broadcasts: a bogus X-Socket-ID makes
        // every toOthers() call throw instead of publishing.
        $middleware->prepend(\App\Http\Middleware\SanitizeSocketId::class);

        $middleware->append(\App\Http\Middleware\InitializeTenancy::class);
        $middleware->append(\App\Http\Middleware\SetUserLanguage::class);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

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
        // The sign-in forms are throttled. Say so on the form, in the member's
        // language, rather than on a bare 429 page.
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, \Illuminate\Http\Request $request) {
            $signIn = $request->is('login') || $request->routeIs('password.email', 'password.update');
            if ($request->expectsJson() || ! $signIn) {
                return null;
            }

            $minutes = max(1, (int) ceil(($e->getHeaders()['Retry-After'] ?? 60) / 60));

            return back()->withInput($request->only('email'))->withErrors([
                'email' => \App\Support\Bilingual::t(
                    "Too many attempts. Try again in {$minutes} minute(s).",
                    "Trop de tentatives. Réessayez dans {$minutes} minute(s)."
                ),
            ]);
        });
    })->create();
