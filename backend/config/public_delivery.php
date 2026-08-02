<?php

$localStaticRoot = in_array(
    (string) env('APP_ENV', 'production'),
    ['local', 'testing', 'staging'],
    true,
)
    ? base_path('../frontend/dist')
    : null;

return [
    /*
    | Laravel serves the current database version of public HTML immediately.
    | The last verified Astro build remains the visual shell and fallback.
    */
    'static_root' => env(
        'STATIC_FRONTEND_ROOT',
        env('FRONTEND_DEPLOYMENT_PUBLIC_ROOT', $localStaticRoot),
    ),
];
