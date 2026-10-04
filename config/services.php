<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

    'metered' => [
        'domain' => env('METERED_DOMAIN'),
        'secret_key' => env('METERED_SECRET_KEY'),
    ],

    /*
     * A relay of your own, or any provider with fixed credentials.
     *
     * Calls between two networks need TURN, and depending on one hosted
     * account for that means the day it answers 401 every such call stops
     * working with no way to intervene from here. Set TURN_URLS (comma
     * separated) and these are used directly — a coturn on the same VPS, or
     * another provider — alongside whatever Metered returns.
     */
    'turn' => [
        'urls' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TURN_URLS', ''))
        ))),
        'username' => env('TURN_USERNAME'),
        'password' => env('TURN_PASSWORD'),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

];
