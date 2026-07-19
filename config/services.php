<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        // Real Cloudflare widget + server-side verification everywhere except
        // local/dev, where a mock is shown. Set TURNSTILE_ENABLED in .env
        // to force it on or off regardless of environment.
        'enabled' => (bool) env('TURNSTILE_ENABLED', ! in_array(env('APP_ENV', 'production'), ['local', 'dev'], true)),
    ],

    'gtm' => [
        'id' => env('GTM_ID'),
        // Load Google Tag Manager everywhere except local/dev by default, so we
        // don't pollute analytics during development. Set GTM_ENABLED in .env
        // to force it on or off regardless of environment.
        'enabled' => (bool) env('GTM_ENABLED', ! in_array(env('APP_ENV', 'production'), ['local', 'dev'], true)),
    ],

];
