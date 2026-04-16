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

        if ($user = $request->user()) {
            $locale = $user->language_pref?->value ?? 'en';
        } elseif ($request->hasHeader('Accept-Language')) {
            $preferred = $request->getPreferredLanguage(['en', 'fr']);
            $locale = $preferred ?? 'en';
        }

        App::setLocale($locale);

        return $next($request);
    }
}
