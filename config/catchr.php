<?php

return [
    'enabled' => env('CATCHR_ENABLED', true),

    'endpoint' => env('CATCHR_ENDPOINT'),

    'timeout' => (int) env('CATCHR_TIMEOUT', 5),

    'environments' => array_filter(explode(',', env('CATCHR_ENVS', 'local,staging,production'))),

    'redact_headers' => ['authorization', 'cookie', 'set-cookie', 'x-csrf-token', 'x-xsrf-token',],

    'redact_keys' => ['password', 'password_confirmation', 'token', 'access_token', 'refresh_token', 'authorization', 'cookie', 'ssn',],

    'server_allow' => ['USER', 'PHP_VERSION', 'SERVER_PROTOCOL', 'SERVER_SOFTWARE', 'REQUEST_METHOD', 'REQUEST_URI', 'REMOTE_ADDR',],
];
