<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable rate limiting globally. When disabled, all requests
    | will pass through without rate limiting.
    |
    */
    'enabled' => env('RATE_LIMIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | When debug mode is enabled, rate limit headers will be added to all
    | responses even if not rate limited. Useful for development.
    |
    */
    'debug' => env('RATE_LIMIT_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Rate Limit Configuration
    |--------------------------------------------------------------------------
    |
    | Define rate limits for different route groups. Each group can have
    | different limits based on the sensitivity of the endpoint.
    |
    | Format: 'max_requests' => 'duration_in_minutes'
    | Example: '5' => '1' means 5 requests per minute
    |
    */
    'limits' => [
        /*
        |--------------------------------------------------------------------------
        | Authentication Routes
        |--------------------------------------------------------------------------
        |
        | Strict rate limits for auth endpoints to prevent brute force attacks.
        | Applied to: login, register, password reset, OTP endpoints
        |
        */
        'auth' => [
            'max_requests' => env('RATE_LIMIT_AUTH_MAX', 5),
            'duration' => env('RATE_LIMIT_AUTH_DURATION', 1), // minutes
            'strategy' => 'sliding_window',
        ],

        /*
        |--------------------------------------------------------------------------
        | OTP Routes
        |--------------------------------------------------------------------------
        |
        | Very strict rate limits for OTP to prevent SMS/Email abuse.
        |
        */
        'otp' => [
            'max_requests' => env('RATE_LIMIT_OTP_MAX', 5),
            'duration' => env('RATE_LIMIT_OTP_DURATION', 1), // minutes
            'strategy' => 'sliding_window',
        ],

        /*
        |--------------------------------------------------------------------------
        | API Routes
        |--------------------------------------------------------------------------
        |
        | Moderate rate limits for general API endpoints.
        |
        */
        'api' => [
            'max_requests' => env('RATE_LIMIT_API_MAX', 60),
            'duration' => env('RATE_LIMIT_API_DURATION', 1), // minutes
            'strategy' => 'sliding_window',
        ],

        /*
        |--------------------------------------------------------------------------
        | Admin Routes
        |--------------------------------------------------------------------------
        |
        | Moderate rate limits for admin dashboard and management routes.
        |
        */
        'admin' => [
            'max_requests' => env('RATE_LIMIT_ADMIN_MAX', 120),
            'duration' => env('RATE_LIMIT_ADMIN_DURATION', 1), // minutes
            'strategy' => 'sliding_window',
        ],

        /*
        |--------------------------------------------------------------------------
        | Public Routes
        |--------------------------------------------------------------------------
        |
        | Relaxed rate limits for public pages and static content.
        |
        */
        'public' => [
            'max_requests' => env('RATE_LIMIT_PUBLIC_MAX', 200),
            'duration' => env('RATE_LIMIT_PUBLIC_DURATION', 1), // minutes
            'strategy' => 'sliding_window',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiter Identifiers
    |--------------------------------------------------------------------------
    |
    | Define the key identifier used for rate limiting. Can be 'ip' or 'user'.
    | When 'user' is selected, authenticated users will be rate limited by user ID.
    | When 'ip' is selected, all requests are rate limited by IP address.
    |
    */
    'identifier' => env('RATE_LIMIT_IDENTIFIER', 'ip'),

    /*
    |--------------------------------------------------------------------------
    | IP Detection
    |--------------------------------------------------------------------------
    |
    | Configure how the client IP is detected. Useful when behind proxies.
    |
    */
    'ip_detection' => [
        'headers' => [
            'X-Forwarded-For',
            'X-Real-IP',
            'X-Cluster-Client-IP',
        ],
        'trust_all_proxies' => env('RATE_LIMIT_TRUST_ALL_PROXIES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Headers
    |--------------------------------------------------------------------------
    |
    | Configure which headers to include in rate limited responses.
    |
    */
    'headers' => [
        'enabled' => env('RATE_LIMIT_HEADERS_ENABLED', true),
        'headers' => [
            'X-RateLimit-Limit' => 'X-RateLimit-Limit',
            'X-RateLimit-Remaining' => 'X-RateLimit-Remaining',
            'Retry-After' => 'Retry-After',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Rate Limit Messages
    |--------------------------------------------------------------------------
    |
    | Custom error messages for different rate limit scenarios.
    |
    */
    'messages' => [
        'default' => 'Too many requests. Please try again later.',
        'auth' => 'Too many authentication attempts. Please try again in :minutes minute(s).',
        'otp' => 'Too many OTP requests. Please try again in :minutes minute(s).',
    ],

    /*
    |--------------------------------------------------------------------------
    | File Based Rate Limiting (Fallback)
    |--------------------------------------------------------------------------
    |
    | If Redis is not available, fall back to file-based rate limiting.
    |
    */
    'file_based_fallback' => env('RATE_LIMIT_FILE_FALLBACK', false),
];