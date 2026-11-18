<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Only relevant to Sanctum's SPA mode, where a first-party browser app
    | authenticates with the session cookie instead of a token. We do not use
    | that mode — the web app is Blade and Livewire on the ordinary `web`
    | guard, and the mobile app sends a Bearer token — so nothing here is
    | consulted in practice. Left at the framework default rather than emptied,
    | so turning SPA mode on later is a one-line change.
    |
    */

    'stateful' => explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | The guards Sanctum checks before falling back to the token, again only in
    | SPA mode.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | Null means a token is valid until it is revoked, which is what we want:
    | the app should not sign a member out on its own. Suspension revokes the
    | token immediately (see EnsureUserActive), and signing out of a device
    | deletes that device's token, so expiry is not what protects us here.
    |
    | The web app takes the same stance — LoginController always passes
    | remember=true because signing in is meant to last until you sign out.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | A prefix lets secret-scanning services recognise a leaked token. Worth
    | setting once the app is public; empty keeps existing tokens valid.
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
