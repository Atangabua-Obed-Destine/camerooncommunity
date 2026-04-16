<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->onboarded_at && ! $request->routeIs('onboarding', 'onboarding.*', 'logout')) {
            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
