<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class StaticFrontendDeploymentService
{
    /**
     * @return array{commit: string, deployed_at: string}
     */
    public function deploy(string $expectedCommit): array
    {
        if (! config('publishing.deployment.enabled')) {
            throw new RuntimeException('Static frontend deployment is disabled.');
        }

        return Cache::lock('static-frontend-deployment', 180)->block(
            15,
            fn (): array => $this->deployLocked($expectedCommit),
        );
    }

    /**
     * @return array{commit: string, deployed_at: string}
     */
    private function deployLocked(string $expectedCommit): array
    {
        $repositoryRoot = realpath(
            (string) config('publishing.deployment.repository_root'),
        );
        $publicRoot = realpath(
            (string) config('publishing.deployment.public_root'),
        );
        $repositoryUrl = (string) config(
            'publishing.deployment.repository_url',
        );
        $branch = (string) config('publishing.deployment.branch');

        if (! $repositoryRoot || ! is_dir($repositoryRoot.'/.git')) {
            throw new RuntimeException('The deployment repository is unavailable.');
        }

        if (
            ! $publicRoot
            || ! is_dir($publicRoot)
            || basename($publicRoot) !== 'public_html'
        ) {
            throw new RuntimeException('The deployment public root is invalid.');
        }

        if (
            ! preg_match('/\A[0-9a-f]{40}\z/', $expectedCommit)
            || $repositoryUrl === ''
            || preg_match('/[\r\n\0]/', $repositoryUrl)
            || ! preg_match('/\A[A-Za-z0-9._\/-]+\z/', $branch)
        ) {
            throw new RuntimeException('The deployment Git target is invalid.');
        }

        $environment = $this->gitEnvironment();
        $deploymentRef = "refs/remotes/deployment/{$branch}";

        $this->run([
            'git',
            'fetch',
            '--force',
            '--no-tags',
            $repositoryUrl,
            "refs/heads/{$branch}:{$deploymentRef}",
        ], $repositoryRoot, $environment, 120);

        $resolvedCommit = trim($this->run([
            'git',
            'rev-parse',
            $deploymentRef,
        ], $repositoryRoot, $environment)->getOutput());

        if (! hash_equals($expectedCommit, $resolvedCommit)) {
            throw new RuntimeException('The requested deployment commit is stale.');
        }

        $temporaryRoot = storage_path(
            'app/private/deployments/'.Str::uuid(),
        );
        $archivePath = $temporaryRoot.'/frontend.tar';
        $extractPath = $temporaryRoot.'/build';

        File::ensureDirectoryExists($extractPath);

        try {
            $this->run([
                'git',
                'archive',
                '--format=tar',
                "--output={$archivePath}",
                $resolvedCommit,
            ], $repositoryRoot, $environment, 120);

            $this->run([
                'tar',
                '-xf',
                $archivePath,
                '-C',
                $extractPath,
            ], $repositoryRoot, [], 120);

            if (
                ! is_file($extractPath.'/index.html')
                || ! is_file($extractPath.'/sitemap.xml')
            ) {
                throw new RuntimeException('The built frontend artifact is incomplete.');
            }

            $this->run([
                'rsync',
                '-az',
                '--checksum',
                '--delete',
                '--delay-updates',
                '--timeout=60',
                '--exclude=.htaccess',
                '--exclude=index.php',
                '--exclude=storage',
                '--exclude=admin.css',
                '--exclude=css/',
                '--exclude=fonts/',
                '--exclude=js/',
                '--exclude=favicon.ico',
                $extractPath.'/',
                $publicRoot.'/',
            ], $repositoryRoot, [], 180);

            if (
                ! is_file($publicRoot.'/index.html')
                || ! is_file($publicRoot.'/sitemap.xml')
            ) {
                throw new RuntimeException('The deployed frontend verification failed.');
            }
        } finally {
            File::deleteDirectory($temporaryRoot);
        }

        return [
            'commit' => $resolvedCommit,
            'deployed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function gitEnvironment(): array
    {
        $keyPath = config('publishing.deployment.ssh_key_path');

        if (! is_string($keyPath) || $keyPath === '') {
            return [];
        }

        $realKeyPath = realpath($keyPath);

        if (! $realKeyPath || ! is_file($realKeyPath)) {
            throw new RuntimeException('The deployment Git SSH key is unavailable.');
        }

        return [
            'GIT_SSH_COMMAND' => sprintf(
                'ssh -i %s -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new',
                escapeshellarg($realKeyPath),
            ),
        ];
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     */
    private function run(
        array $command,
        string $workingDirectory,
        array $environment = [],
        int $timeout = 60,
    ): Process {
        $process = new Process(
            $command,
            $workingDirectory,
            $environment === [] ? null : $environment,
            null,
            $timeout,
        );
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'Static frontend deployment failed: '.
                trim($process->getErrorOutput() ?: $process->getOutput()),
            );
        }

        return $process;
    }
}
