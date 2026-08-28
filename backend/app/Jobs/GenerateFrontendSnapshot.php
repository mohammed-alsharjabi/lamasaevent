<?php

namespace App\Jobs;

use App\Models\PublishJob;
use App\Services\ContentExportService;
use App\Services\ContentSnapshotGitPublisher;
use App\Services\ContentSnapshotWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateFrontendSnapshot implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public int $publishJobId) {}

    /**
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('frontend-snapshot'))
                ->releaseAfter(5)
                ->expireAfter($this->timeout + 30),
        ];
    }

    public function handle(
        ContentExportService $exporter,
        ContentSnapshotWriter $writer,
        ContentSnapshotGitPublisher $gitPublisher,
    ): void {
        $job = PublishJob::findOrFail($this->publishJobId);
        $job->update([
            'status' => 'running',
            'started_at' => now(),
            'attempts' => $job->attempts + 1,
        ]);

        $payload = $exporter->build();
        $json = $writer->encode($payload);
        $version = substr(hash('sha256', $json), 0, 16);
        $path = "publish/snapshots/content-{$version}.json";

        if (! Storage::disk('local')->put($path, $json)) {
            throw new RuntimeException('Could not persist the versioned content snapshot.');
        }

        $frontendSnapshot = config('publishing.frontend_snapshot_path');

        if (is_string($frontendSnapshot) && $frontendSnapshot !== '') {
            $writer->write($frontendSnapshot, $json);
        }

        $gitPublisher->publish($version);

        $job->update([
            'status' => 'completed',
            'snapshot_path' => $path,
            'build_version' => $version,
            'finished_at' => now(),
            'failure_reason' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Frontend snapshot generation failed.', [
            'publish_job_id' => $this->publishJobId,
            'exception' => $exception,
        ]);

        PublishJob::whereKey($this->publishJobId)->update([
            'status' => 'failed',
            'finished_at' => now(),
            'failure_reason' => 'تعذر توليد نسخة الواجهة. راجع سجل الخادم باستخدام رقم العملية.',
        ]);
    }
}
