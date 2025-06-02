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

    // Base URL for OAuth callbacks
    'oauth_base_url' => env('APP_URL', 'http://localhost:8000') . '/api/auth/callback',

    // OAuth providers configuration
    'oauth_providers' => [
        'google' => [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        ],
        'facebook' => [
            'client_id' => env('FACEBOOK_CLIENT_ID'),
            'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        ],
    ],

    // Legacy support - maintain backward compatibility
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('APP_URL', 'http://localhost:8000') . '/api/auth/callback/google',
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('APP_URL', 'http://localhost:8000') . '/api/auth/callback/facebook',
    ],

    // Next.js configuration
    'nextjs_url' => env('NEXTJS_URL', 'http://localhost:3000'),
];
