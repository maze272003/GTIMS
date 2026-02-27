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
            'advanced_analytics' => (bool) env('FEATURE_ADVANCED_ANALYTICS', false),
            'cross_barangay_requests' => (bool) env('FEATURE_CROSS_BARANGAY_REQUESTS', false),
            'custom_branding' => (bool) env('FEATURE_CUSTOM_BRANDING', false),
            'webhooks' => (bool) env('FEATURE_WEBHOOKS', false),
            'api_access' => (bool) env('FEATURE_API_ACCESS', false),
        ],
        'kill_switches' => [
            'tenant_routes' => (bool) env('FEATURE_TENANT_ROUTES', true),
            'tenant_exports' => (bool) env('FEATURE_TENANT_EXPORTS', true),
            'tenant_notifications' => (bool) env('FEATURE_TENANT_NOTIFICATIONS', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'tenant_api_per_minute' => (int) env('TENANCY_RATE_LIMIT_API', 100),
        'tenant_login_per_minute' => (int) env('TENANCY_RATE_LIMIT_LOGIN', 5),
        'moderator_login_per_minute' => (int) env('TENANCY_RATE_LIMIT_MODERATOR_LOGIN', 10),
        'tenant_export_per_hour' => (int) env('TENANCY_RATE_LIMIT_EXPORT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Security
    |--------------------------------------------------------------------------
    */
    'session_security' => [
        'invalidate_on_membership_change' => (bool) env('TENANCY_SESSION_INVALIDATE_ON_MEMBERSHIP_CHANGE', true),
        'invalidate_on_role_change' => (bool) env('TENANCY_SESSION_INVALIDATE_ON_ROLE_CHANGE', true),
        'bind_tenant_in_session' => (bool) env('TENANCY_SESSION_BIND_TENANT', true),
    ],

    'legacy_admin' => [
        'enabled' => (bool) env('TENANCY_LEGACY_ADMIN_ENABLED', true),
        'moderator_only' => (bool) env('TENANCY_LEGACY_ADMIN_MODERATOR_ONLY', true),
    ],

    'allow_legacy_unscoped_records' => (bool) env('TENANCY_ALLOW_LEGACY_UNSCOPED', true),

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
