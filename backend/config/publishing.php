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

    /*
    |--------------------------------------------------------------------------
    | Static frontend Git publisher
    |--------------------------------------------------------------------------
    |
    | Shared hosting cannot run Astro itself. When enabled, the snapshot job
    | commits only the generated content export and pushes it to the configured
    | branch. GitHub Actions then builds Astro and deploys the static artifact.
    |
    */
    'git' => [
        'enabled' => (bool) env('FRONTEND_GIT_PUBLISH_ENABLED', false),
        'repository_root' => env(
            'FRONTEND_GIT_REPOSITORY_ROOT',
            base_path('..'),
        ),
        'snapshot_relative_path' => env(
            'FRONTEND_GIT_SNAPSHOT_PATH',
            'frontend/src/data/content-export.json',
        ),
        'remote' => env('FRONTEND_GIT_REMOTE', 'origin'),
        'branch' => env('FRONTEND_GIT_BRANCH', 'feature/admin-cms'),
        'ssh_key_path' => env('FRONTEND_GIT_SSH_KEY_PATH'),
    ],
];
