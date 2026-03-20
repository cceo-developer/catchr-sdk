<?php

return [
    'enabled' => env('CATCHR_ENABLED', true),
    'environments' => array_values(array_filter(array_map('trim', explode(',', (string) env('CATCHR_ENVS', 'local,staging,production'))))),

    'redact_headers' => ['authorization', 'cookie', 'set-cookie', 'x-csrf-token', 'x-xsrf-token',],
    'redact_keys' => ['password', 'password_confirmation', 'token', 'access_token', 'refresh_token', 'authorization', 'cookie', 'ssn',],
    'server_allow' => ['USER', 'PHP_VERSION', 'SERVER_PROTOCOL', 'SERVER_SOFTWARE', 'REQUEST_METHOD', 'REQUEST_URI', 'REMOTE_ADDR',],

    'exception' => [
        'enabled' => env('CATCHR_EXCEPTION_ENABLED', true),
        'endpoints' => array_values(array_filter(array_map('trim', explode(',', (string) env('CATCHR_EXCEPTION_ENDPOINTS', 'https://api.catchr.dev/api/exceptions'))))),
        'timeout' => (int) env('CATCHR_EXCEPTION_TIMEOUT', 5),
        'dedupe' => [
            'enabled' => (bool) env('CATCHR_EXCEPTION_DEDUPE_ENABLED', true),
            'ttl_seconds' => (int) env('CATCHR_EXCEPTION_DEDUPE_TTL', 300),
            'cache_store' => env('CATCHR_EXCEPTION_DEDUPE_STORE', null), // null => default cache
            'prefix' => env('CATCHR_EXCEPTION_DEDUPE_PREFIX', 'catchr:seen:'),
            'normalize_message' => (bool) env('CATCHR_EXCEPTION_DEDUPE_NORMALIZE_MESSAGE', true),
        ],
    ],

    'queue' => [
        'enabled' => (bool) env('CATCHR_QUEUE_ENABLED', true),
        'report_processing' => (bool) env('CATCHR_QUEUE_REPORT_PROCESSING', true),
        'report_processed' => (bool) env('CATCHR_QUEUE_REPORT_PROCESSED', true),
        'report_failed' => (bool) env('CATCHR_QUEUE_REPORT_FAILED', true),
        'endpoints' => array_values(array_filter(array_map('trim', explode(',', (string) env('CATCHR_QUEUE_ENDPOINTS', 'https://api.catchr.dev/api/jobs'))))),
        'timeout' => (int) env('CATCHR_TIMEOUT', 5),
    ],

    'log' => [
        'enabled' => (bool) env('CATCHR_LOG_ENABLED', true),
        'endpoints' => array_values(array_filter(array_map('trim', explode(',', (string) env('CATCHR_LOG_ENDPOINTS', 'https://api.catchr.dev/api/logs'))))),
        'timeout' => (int) env('CATCHR_TIMEOUT', 5),
    ],

    'public_key' => env('CATCHR_PUBLIC_KEY', null),
    'private_key' => env('CATCHR_PRIVATE_KEY', null),
];
