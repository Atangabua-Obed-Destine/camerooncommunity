<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            if (! $user->is_active) {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('error', __('Your account is not active.'));
            }

            // Track last activity (throttled: once per minute)
            if (! $user->last_active_at || $user->last_active_at->lt(now()->subMinute())) {
                $user->updateQuietly(['last_active_at' => now()]);
            }
        }

        return $next($request);
    }
}
