<?php

return [
    'enabled' => env('RATE_LIMIT_ENABLED', true),

    'driver' => env('RATE_LIMIT_DRIVER', 'redis'),

    'redis' => [
        'connection' => env('RATE_LIMIT_REDIS_CONNECTION', 'default'),
        'prefix' => env('RATE_LIMIT_REDIS_PREFIX', 'ratelimit:'),
    ],

    'limits' => [
        'auth' => [
            'max_requests' => env('RATE_LIMIT_AUTH_MAX', 5),
            'decay_seconds' => env('RATE_LIMIT_AUTH_DECAY', 60),
            'description' => 'Login, register, password reset, OTP endpoints',
        ],
        'otp' => [
            'max_requests' => env('RATE_LIMIT_OTP_MAX', 5),
            'decay_seconds' => env('RATE_LIMIT_OTP_DECAY', 60),
            'description' => 'OTP send and verify endpoints',
        ],
        'api' => [
            'max_requests' => env('RATE_LIMIT_API_MAX', 60),
            'decay_seconds' => env('RATE_LIMIT_API_DECAY', 60),
            'description' => 'General API endpoints (authenticated)',
        ],
        'admin' => [
            'max_requests' => env('RATE_LIMIT_ADMIN_MAX', 120),
            'decay_seconds' => env('RATE_LIMIT_ADMIN_DECAY', 60),
            'description' => 'Admin panel endpoints',
        ],
        'public' => [
            'max_requests' => env('RATE_LIMIT_PUBLIC_MAX', 30),
            'decay_seconds' => env('RATE_LIMIT_PUBLIC_DECAY', 60),
            'description' => 'Public pages and search',
        ],
        'export' => [
            'max_requests' => env('RATE_LIMIT_EXPORT_MAX', 10),
            'decay_seconds' => env('RATE_LIMIT_EXPORT_DECAY', 300),
            'description' => 'Export operations (PDF, Excel)',
        ],
    ],

    'identifiers' => [
        'use_ip' => env('RATE_LIMIT_USE_IP', true),
        'use_user_id' => env('RATE_LIMIT_USE_USER_ID', true),
        'fallback_to_ip' => env('RATE_LIMIT_FALLBACK_TO_IP', true),
    ],

    'response' => [
        'status_code' => 429,
        'message' => 'Too many requests. Please try again later.',
        'include_retry_after' => true,
        'include_limit_headers' => true,
    ],

    'storage' => [
        'ttl' => env('RATE_LIMIT_STORAGE_TTL', 86400),
    ],

    'debug' => env('RATE_LIMIT_DEBUG', false),

    'log' => [
        'enabled' => env('RATE_LIMIT_LOG_ENABLED', true),
        'channel' => env('RATE_LIMIT_LOG_CHANNEL', 'daily'),
    ],
];