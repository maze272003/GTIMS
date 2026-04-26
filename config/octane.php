<?php

use Illuminate\Support\Str;

return [
    'server' => 'roadrunner',

    'listen' => '127.0.0.1:8000',

    'workers' => (int) (Str::of(php_uname('m'))->contains('arm') || Str::of(php_uname('m'))->contains('aarch64') ? 4 : 2) * 4,

    'max_requests' => 1000,

    'task_max_time' => 300,

    'max_job_time' => 60,

    'max_memory' => 128,

    'cache' => [
        'enabled' => true,
        'driver' => 'redis',
        'ttl' => 3600,
    ],

    'tables' => [
        'suggested' => 1000,
        'max' => 10000,
    ],

    'garbage_collection' => [
        'enabled' => true,
        'probability' => 10,
    ],

    'http' => [
        'max_request_body_size' => 1024 * 1024 * 8,
        'fastcgi' => false,
    ],

    'untrusted_proxies' => '*',

    'stateful' => [
        'localhost',
        '127.0.0.1',
    ],

    'routing' => [
        'cache' => false,
    ],

    'warm' => [
        'octane_warm_route_cache' => false,
        'octane_warm_config_cache' => false,
        'octane_warm_view_cache' => true,
    ],

    'errors' => [
        'log_output' => 'stderr',
    ],
];