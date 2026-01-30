<?php

return [
    'enabled' => env('CATCHR_ENABLED', true),

    'endpoint' => env('CATCHR_ENDPOINT'),

    'timeout' => (int) env('CATCHR_TIMEOUT', 5),

    'environments' => array_filter(explode(',', env('CATCHR_ENVS', 'local,staging,production'))),
];
