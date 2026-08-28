<?php

namespace Tests\Unit;

use App\Services\StaticFrontendDeploymentService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class StaticFrontendDeploymentServiceTest extends TestCase
{
    public function test_it_deploys_the_exact_built_commit_and_preserves_laravel_files(): void
    {
        $root = storage_path('framework/testing/static-deployment-'.Str::uuid());
        $remote = $root.'/remote.git';
        $source = $root.'/source';
        $repository = $root.'/repository';
        $publicRoot = $root.'/public_html';

        try {
            File::ensureDirectoryExists($remote);
            File::ensureDirectoryExists($source);
            File::ensureDirectoryExists($repository);
            File::ensureDirectoryExists($publicRoot.'/css');

            $this->git(['init', '--bare'], $remote);
            $this->git(['init', '-b', 'staging-dist'], $source);
            File::put($source.'/index.html', '<h1>New build</h1>');
            File::put($source.'/sitemap.xml', '<urlset/>');
            File::ensureDirectoryExists($source.'/assets');
            File::put($source.'/assets/app.css', 'body{}');
            $this->git(['add', '.'], $source);
            $this->git([
                '-c', 'user.name=Test',
                '-c', 'user.email=test@example.test',
                'commit', '-m', 'built frontend',
            ], $source);
            $commit = trim($this->git(['rev-parse', 'HEAD'], $source));
            $this->git(['push', $remote, 'staging-dist'], $source);

            $this->git(['init', '-b', 'feature/admin-cms'], $repository);
            File::put($repository.'/README.md', 'application');
            $this->git(['add', '.'], $repository);
            $this->git([
                '-c', 'user.name=Test',
                '-c', 'user.email=test@example.test',
                'commit', '-m', 'application',
            ], $repository);

            File::put($publicRoot.'/index.html', '<h1>Old build</h1>');
            File::put($publicRoot.'/stale.txt', 'remove me');
            File::put($publicRoot.'/index.php', 'protected PHP front controller');
            File::put($publicRoot.'/.htaccess', 'protected routing');
            File::put($publicRoot.'/admin.css', 'protected admin asset');
            File::put($publicRoot.'/css/admin.css', 'protected admin directory');

            config([
                'publishing.deployment.enabled' => true,
                'publishing.deployment.repository_root' => $repository,
                'publishing.deployment.repository_url' => $remote,
                'publishing.deployment.branch' => 'staging-dist',
                'publishing.deployment.public_root' => $publicRoot,
                'publishing.deployment.ssh_key_path' => null,
            ]);

            $result = app(StaticFrontendDeploymentService::class)
                ->deploy($commit);

            $this->assertSame($commit, $result['commit']);
            $this->assertSame('<h1>New build</h1>', File::get($publicRoot.'/index.html'));
            $this->assertFileExists($publicRoot.'/sitemap.xml');
            $this->assertFileExists($publicRoot.'/assets/app.css');
            $this->assertFileDoesNotExist($publicRoot.'/stale.txt');
            $this->assertSame(
                'protected PHP front controller',
                File::get($publicRoot.'/index.php'),
            );
            $this->assertSame(
                'protected routing',
                File::get($publicRoot.'/.htaccess'),
            );
            $this->assertSame(
                'protected admin asset',
                File::get($publicRoot.'/admin.css'),
            );
            $this->assertSame(
                'protected admin directory',
                File::get($publicRoot.'/css/admin.css'),
            );
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
