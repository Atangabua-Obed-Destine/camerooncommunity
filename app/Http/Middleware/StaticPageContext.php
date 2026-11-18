<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * About, Contact, Privacy and Terms, which the mobile app opens in an in-app
 * browser (SFSafariViewController) rather than rebuilding them.
 *
 *   ?lang=en|fr  the page's language. The in-app browser shares no storage with
 *                the app or with Safari, so the app has to say it every time.
 *                Works for any visitor, which also makes a French link shareable.
 *   ?app=1       hide what belongs to the website, not the app: the marketing
 *                navbar, the Kamer bubble, the install banner, and the links out
 *                to sign-up and the landing page.
 *
 * Views get $inApp, and $staticQuery to carry both through links between the
 * four pages, so following "Terms" from Privacy stays in the app's mode.
 */
class StaticPageContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $lang = $request->query('lang');
        if (in_array($lang, ['en', 'fr'], true)) {
            App::setLocale($lang);
        }

        $inApp = $request->query('app') === '1';

        View::share('inApp', $inApp);
        View::share('staticQuery', array_filter([
            'app' => $inApp ? 1 : null,
            'lang' => in_array($lang, ['en', 'fr'], true) ? $lang : null,
        ]));

        return $next($request);
    }
}
