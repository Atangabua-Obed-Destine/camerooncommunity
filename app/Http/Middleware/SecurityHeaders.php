<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The standard browser protections, which no response sent before.
 *
 * No Content-Security-Policy: Alpine and Livewire rely on inline handlers and
 * evaluated expressions, so a CSP needs its own careful rollout (report-only
 * first). These headers break nothing.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        // Nobody else can frame the site (clickjacking).
        $headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        // Location for the city rooms, camera and microphone for photos and
        // (later) calls, all for this site only; nothing else.
        $headers->set('Permissions-Policy', 'geolocation=(self), camera=(self), microphone=(self), payment=(), usb=()', false);

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        return $response;
    }
}
