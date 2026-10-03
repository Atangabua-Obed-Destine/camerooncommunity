<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes an audit entry for every change made from the admin panel.
 *
 * Before this, only two moderation actions logged anything, so the audit page
 * answered almost nothing: settings changes, role grants, ad edits and campaign
 * approvals all happened invisibly. Doing it as middleware means new admin
 * actions are covered the day they are written, with nothing to remember.
 *
 * Reads are not logged here — they would bury the record — except for the ones
 * that deserve it, which log themselves (see AdminController::userLocations).
 */
class LogsAdminActions
{
    /** Never record these verbatim, even inside the admin panel. */
    private const REDACT = ['password', 'password_confirmation', 'token', 'secret', 'api_key'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethodSafe() || ! auth()->check()) {
            return $response;
        }

        // A redirect back with an error means nothing changed.
        if ($response->isServerError()) {
            return $response;
        }

        $payload = collect($request->except(['_token', '_method']))
            ->map(fn ($value, $key) => in_array($key, self::REDACT, true) ? '[redacted]' : $value)
            ->filter(fn ($value) => ! is_object($value) && ! is_resource($value))
            ->take(20)
            ->all();

        activity()
            ->causedBy(auth()->user())
            ->withProperties([
                'route'  => optional($request->route())->getName() ?: $request->path(),
                'method' => $request->method(),
                'params' => optional($request->route())->parameters()
                    ? array_map(
                        fn ($p) => is_object($p) ? ($p->id ?? class_basename($p)) : $p,
                        $request->route()->parameters()
                    )
                    : [],
                'input'  => $payload,
                'ip'     => $request->ip(),
            ])
            ->log('Admin action');

        return $response;
    }
}
