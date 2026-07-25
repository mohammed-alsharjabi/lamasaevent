<?php

$defaultFrontendSnapshot = in_array(
    (string) env('APP_ENV', 'production'),
    ['local', 'staging'],
    true,
)
    ? base_path('../frontend/src/data/content-export.json')
    : null;

return [
    /*
    |--------------------------------------------------------------------------
    | Active Astro content snapshot
    |--------------------------------------------------------------------------
    |
    | Local and monorepo staging environments atomically refresh Astro's
    | watched content file after every CMS save. Production deployments can
    | set a shared release path, or leave this null and consume the versioned
    | snapshot through the deployment pipeline.
    |
    */
    'frontend_snapshot_path' => env(
        'FRONTEND_CONTENT_SNAPSHOT_PATH',
        $defaultFrontendSnapshot,
    ),
];
