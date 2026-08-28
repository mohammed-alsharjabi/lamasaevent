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

    /*
    |--------------------------------------------------------------------------
    | Built frontend deployment webhook
    |--------------------------------------------------------------------------
    |
    | GitHub Actions publishes the verified Astro build to an isolated branch,
    | then calls a signed Laravel webhook. This outbound-only flow works on
    | shared hosts that block inbound SSH from GitHub runners and do not ship
    | Node.js or cron.
    |
    */
    'deployment' => [
        'enabled' => (bool) env('FRONTEND_DEPLOYMENT_ENABLED', false),
        'webhook_secret' => env('FRONTEND_DEPLOYMENT_WEBHOOK_SECRET'),
        'repository_root' => env(
            'FRONTEND_DEPLOYMENT_REPOSITORY_ROOT',
            base_path('..'),
        ),
        'repository_url' => env(
            'FRONTEND_DEPLOYMENT_REPOSITORY_URL',
            'git@github.com:mohammed-alsharjabi/lamasaevent.git',
        ),
        'branch' => env('FRONTEND_DEPLOYMENT_BRANCH', 'staging-dist'),
        'public_root' => env('FRONTEND_DEPLOYMENT_PUBLIC_ROOT'),
        'ssh_key_path' => env(
            'FRONTEND_DEPLOYMENT_SSH_KEY_PATH',
            env('FRONTEND_GIT_SSH_KEY_PATH'),
        ),
        'max_signature_age' => (int) env(
            'FRONTEND_DEPLOYMENT_SIGNATURE_MAX_AGE',
            300,
        ),
    ],
];
