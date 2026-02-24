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

];
