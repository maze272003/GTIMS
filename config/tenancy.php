<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant Identification Strategy
    |--------------------------------------------------------------------------
    |
    | How tenants are identified in incoming requests.
    | Supported: "path" (/{provinceSlug}/{barangaySlug})
    |
    */
    'identification' => 'path',

    /*
    |--------------------------------------------------------------------------
    | Moderator Route Prefix
    |--------------------------------------------------------------------------
    |
    | The URL prefix for the moderator (super-admin) portal.
    |
    */
    'moderator_prefix' => 'moderator',

    /*
    |--------------------------------------------------------------------------
    | System Roles
    |--------------------------------------------------------------------------
    |
    | Default system role slugs used for RBAC.
    |
    */
    'roles' => [
        'moderator' => [
            'name' => 'Moderator',
            'slug' => 'moderator',
            'scope_type' => 'platform',
        ],
        'province_admin' => [
            'name' => 'Provincial Administrator',
            'slug' => 'province-admin',
            'scope_type' => 'province',
        ],
        'barangay_admin' => [
            'name' => 'Barangay Administrator',
            'slug' => 'barangay-admin',
            'scope_type' => 'barangay',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Session Keys
    |--------------------------------------------------------------------------
    |
    | Session keys used to store tenant context.
    |
    */
    'session_keys' => [
        'scope_type' => 'tenant.scope_type',
        'province_id' => 'tenant.province_id',
        'barangay_id' => 'tenant.barangay_id',
        'province_slug' => 'tenant.route_slug_province',
        'barangay_slug' => 'tenant.route_slug_barangay',
    ],

    /*
    |--------------------------------------------------------------------------
    | Invitation Settings
    |--------------------------------------------------------------------------
    */
    'invitation' => [
        'expires_days' => env('TENANCY_INVITATION_EXPIRE_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Usage Quotas
    |--------------------------------------------------------------------------
    */
    'quotas' => [
        'barangay' => [
            'users' => 10,
            'inventory_items' => 5000,
            'patient_records' => 50000,
            'storage_mb' => 500,
            'api_calls_daily' => 10000,
            'exports_monthly' => 100,
        ],
        'province' => [
            'users' => 100,
            'barangays' => 50,
            'storage_mb' => 5000,
            'api_calls_daily' => 100000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */
    'features' => [
        'defaults' => [
            'advanced_analytics' => false,
            'cross_barangay_requests' => false,
            'custom_branding' => false,
            'webhooks' => false,
            'api_access' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Settings
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'disk' => env('TENANCY_STORAGE_DISK', 'local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'prefix' => env('TENANCY_CACHE_PREFIX', 'tenant'),
    ],

];
