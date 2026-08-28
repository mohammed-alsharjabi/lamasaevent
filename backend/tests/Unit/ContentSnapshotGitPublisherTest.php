<?php

namespace Tests\Unit;

use App\Services\ContentSnapshotGitPublisher;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ContentSnapshotGitPublisherTest extends TestCase
{
    public function test_disabled_publisher_does_not_require_a_repository(): void
    {
        config([
            'publishing.git.enabled' => false,
            'publishing.git.repository_root' => '/missing/repository',
        ]);

        app(ContentSnapshotGitPublisher::class)->publish('disabled');

        $this->assertTrue(true);
    }

    public function test_snapshot_is_committed_and_pushed_to_the_configured_branch(): void
    {
        $root = storage_path('framework/testing/git-publisher-'.Str::uuid());
        $repository = $root.'/repository';
        $remote = $root.'/remote.git';
        $snapshot = 'frontend/src/data/content-export.json';

        try {
            File::ensureDirectoryExists(dirname($repository.'/'.$snapshot));
            File::ensureDirectoryExists($remote);
            $this->git(['init', '--bare'], $remote);
            $this->git(['init', '-b', 'feature/admin-cms'], $repository);
            File::put($repository.'/'.$snapshot, '{"version":1}');
            $this->git(['add', '--', $snapshot], $repository);
            $this->git([
                '-c', 'user.name=Test',
                '-c', 'user.email=test@example.test',
                'commit', '-m', 'initial',
            ], $repository);

            File::put($repository.'/'.$snapshot, '{"version":2}');
            config([
                'publishing.git.enabled' => true,
                'publishing.git.repository_root' => $repository,
                'publishing.git.snapshot_relative_path' => $snapshot,
                'publishing.git.remote' => $remote,
                'publishing.git.branch' => 'feature/admin-cms',
                'publishing.git.ssh_key_path' => null,
            ]);

            app(ContentSnapshotGitPublisher::class)->publish('abc123');

            $published = $this->git([
                '--git-dir='.$remote,
                'show',
                'feature/admin-cms:'.$snapshot,
            ], $root);

            $this->assertSame('{"version":2}', trim($published));
        } finally {
            File::deleteDirectory($root);
        }
    }

    /**
     * @param  list<string>  $arguments
     */
    private function git(array $arguments, string $workingDirectory): string
    {
        $process = new Process(['git', ...$arguments], $workingDirectory);
        $process->mustRun();

        return $process->getOutput();
    }
}
