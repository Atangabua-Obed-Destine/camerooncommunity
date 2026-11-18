<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetUserLanguage
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = 'en';

        // The mobile app states its language outright, as the E-ride client
        // does. It has to: this middleware is global, so it runs before any
        // token has been resolved and `$request->user()` is still null on an
        // API request — the saved preference is simply not available yet.
        // No browser sends this header, so the web path below is untouched.
        if ($header = $request->header('X-Localization')) {
            if (in_array($header, ['en', 'fr'], true)) {
                $locale = $header;
            }
        } elseif ($user = $request->user()) {
            $locale = $user->language_pref?->value ?? 'en';
        } elseif ($request->hasHeader('Accept-Language')) {
            $preferred = $request->getPreferredLanguage(['en', 'fr']);
            $locale = $preferred ?? 'en';
        }

        App::setLocale($locale);

        return $next($request);
    }
}
