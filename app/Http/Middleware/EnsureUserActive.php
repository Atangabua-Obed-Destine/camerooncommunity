<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            if (! $user->is_active) {
                return $this->turnAway($request, $user);
            }

            // Track last activity (throttled: once per minute)
            if (! $user->last_active_at || $user->last_active_at->lt(now()->subMinute())) {
                $user->updateQuietly(['last_active_at' => now()]);
            }
        }

        return $next($request);
    }

    /**
     * Stop a suspended account, in whichever way the caller can understand.
     *
     * A browser gets the session torn down and a redirect, as it always has.
     * The app cannot: there is no session to invalidate, and `auth()->logout()`
     * does not exist on the token guard — calling it would throw instead of
     * refusing the request.
     *
     * Worse, a token outlives the moment it was issued. Suspending someone has
     * to take their tokens away too, or the phone in their pocket keeps working
     * until they happen to sign out. So we revoke every one of them.
     */
    private function turnAway(Request $request, User $user): Response
    {
        $message = __('Your account is not active.');

        // Only a token-authenticated request has one of these; a session or
        // Livewire request returns null and takes the browser path below.
        if ($user->currentAccessToken()) {
            $user->tokens()->delete();

            return response()->json(['message' => $message], 403);
        }

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('error', $message);
    }
}
