<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class ContentSnapshotGitPublisher
{
    public function publish(string $version): void
    {
        if (! config('publishing.git.enabled')) {
            return;
        }

        $repositoryRoot = realpath((string) config('publishing.git.repository_root'));
        $relativePath = (string) config('publishing.git.snapshot_relative_path');
        $remote = (string) config('publishing.git.remote');
        $branch = (string) config('publishing.git.branch');

        if (! $repositoryRoot || ! is_dir($repositoryRoot.'/.git')) {
            throw new RuntimeException('The frontend Git repository is unavailable.');
        }

        if (
            $relativePath === ''
            || str_starts_with($relativePath, '/')
            || str_contains($relativePath, '..')
            || ! is_file($repositoryRoot.'/'.$relativePath)
        ) {
            throw new RuntimeException('The frontend snapshot path is invalid.');
        }

        if (
            $remote === ''
            || $branch === ''
            || str_contains($remote, "\0")
            || preg_match('/[\r\n]/', $remote)
            || ! preg_match('/\A[A-Za-z0-9._\/-]+\z/', $branch)
        ) {
            throw new RuntimeException('The frontend Git target is invalid.');
        }

        $environment = $this->gitEnvironment();

        $this->run(
            ['git', 'add', '--', $relativePath],
            $repositoryRoot,
            $environment,
        );

        $diff = $this->run(
            ['git', 'diff', '--cached', '--quiet', '--', $relativePath],
            $repositoryRoot,
            $environment,
            false,
        );

        if ($diff->getExitCode() === 1) {
            $this->run([
                'git',
                '-c', 'user.name=Lamsa CMS',
                '-c', 'user.email=cms@lams-event.com',
                'commit',
                '--only',
                '-m', "content: publish snapshot {$version}",
                '--',
                $relativePath,
            ], $repositoryRoot, $environment);
        } elseif (! $diff->isSuccessful()) {
            throw new RuntimeException('Could not inspect the frontend snapshot changes.');
        }

        $this->run(
            ['git', 'push', $remote, "HEAD:{$branch}"],
            $repositoryRoot,
            $environment,
        );
    }

    /**
     * @return array<string, string>
     */
    private function gitEnvironment(): array
    {
        $keyPath = config('publishing.git.ssh_key_path');

        if (! is_string($keyPath) || $keyPath === '') {
            return [];
        }

        $realKeyPath = realpath($keyPath);

        if (! $realKeyPath || ! is_file($realKeyPath)) {
            throw new RuntimeException('The frontend Git SSH key is unavailable.');
        }

        return [
            'GIT_SSH_COMMAND' => sprintf(
                'ssh -i %s -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new',
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
        array $environment,
        bool $mustSucceed = true,
    ): Process {
        $process = new Process(
            $command,
            $workingDirectory,
            $environment === [] ? null : $environment,
            null,
            60,
        );
        $process->run();

        if ($mustSucceed && ! $process->isSuccessful()) {
            throw new RuntimeException(
                'Frontend Git publishing failed: '.trim($process->getErrorOutput()),
            );
        }

        return $process;
    }
}
