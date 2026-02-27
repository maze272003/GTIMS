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
            'require_2fa' => (bool) env('FEATURE_REQUIRE_2FA', false),
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

    'rbac' => [
        'allow_legacy_permissions' => (bool) env('TENANCY_ALLOW_LEGACY_PERMISSIONS', true),
        'allow_legacy_moderator_fallback' => (bool) env('TENANCY_ALLOW_LEGACY_MODERATOR_FALLBACK', true),
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

    /*
    |--------------------------------------------------------------------------
    | Seeder Settings
    |--------------------------------------------------------------------------
    */
    'seeder' => [
        'seed_all_geo' => (bool) env('TENANCY_SEED_ALL_GEO', true),
        'activate_seeded_geo' => (bool) env('TENANCY_SEED_ACTIVATE_GEO', true),
        'psgc_base_url' => env('TENANCY_PSGC_BASE_URL', 'https://psgc.gitlab.io/api'),
        'demo_provinces' => array_values(array_filter(array_map(
            static fn (string $slug) => trim($slug),
            explode(',', (string) env('TENANCY_DEMO_PROVINCES', 'bulacan,cebu,davao-del-sur'))
        ))),
        'demo_barangays_per_province' => (int) env('TENANCY_DEMO_BARANGAYS_PER_PROVINCE', 5),
        'demo_records_per_module' => (int) env('TENANCY_DEMO_RECORDS_PER_MODULE', 50),
        'chunk_size' => (int) env('TENANCY_SEEDER_CHUNK_SIZE', 500),
        'moderator_email' => env('TENANCY_MODERATOR_EMAIL', 'moderator@gtims.local'),
        'moderator_name' => env('TENANCY_MODERATOR_NAME', 'GTIMS Moderator'),
        'default_password' => env('TENANCY_DEMO_PASSWORD', 'password'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Foreign-Key Validation
    |--------------------------------------------------------------------------
    |
    | Request input paths mapped to database tables that must match current
    | tenant context when using tenant routes.
    |
    */
    'tenant_fk_validation' => [
        'province_id' => 'provinces',
        'barangay_id' => 'barangays',
        'branch_id' => 'branches',
        'inventory_id' => 'inventories',
        'supplier_id' => 'suppliers',
        'items.*.inventory_id' => 'inventories',
        'items.*.supplier_id' => 'suppliers',
        'items.*.branch_id' => 'branches',
        'medications.*.name' => 'inventories',
        'medications.*.inventory_id' => 'inventories',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'events' => [
            'inventory.low_stock',
            'request.created',
            'request.status_changed',
            'export.completed',
            'incident.created',
            'incident.resolved',
            'membership.suspended',
        ],
        'delivery_timeout_seconds' => (int) env('TENANCY_WEBHOOK_TIMEOUT', 10),
        'max_retries' => (int) env('TENANCY_WEBHOOK_MAX_RETRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Access
    |--------------------------------------------------------------------------
    */
    'api' => [
        'enabled' => (bool) env('FEATURE_API_ACCESS', false),
        'token_ttl_minutes' => (int) env('TENANCY_API_TOKEN_TTL', 1440),
    ],

    'billing' => [
        'enabled' => (bool) env('BILLING_ENABLED', false),
        'provider' => env('BILLING_PROVIDER', 'none'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PII Controls
    |--------------------------------------------------------------------------
    */
    'pii' => [
        'patientrecords' => ['patient_name', 'purok'],
        'users' => ['email'],
    ],

];
