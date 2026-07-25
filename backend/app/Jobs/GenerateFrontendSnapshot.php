<?php

namespace App\Jobs;

use App\Models\PublishJob;
use App\Services\ContentExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateFrontendSnapshot implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public int $publishJobId) {}

    public function handle(ContentExportService $exporter): void
    {
        $job = PublishJob::findOrFail($this->publishJobId);
        $job->update([
            'status' => 'running',
            'started_at' => now(),
            'attempts' => $job->attempts + 1,
        ]);

        $payload = $exporter->build();
        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        $version = substr(hash('sha256', $json), 0, 16);
        $path = "publish/snapshots/content-{$version}.json";

        Storage::disk('local')->put($path, $json);
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
        PublishJob::whereKey($this->publishJobId)->update([
            'status' => 'failed',
            'finished_at' => now(),
            'failure_reason' => $exception?->getMessage() ?? 'Unknown snapshot failure.',
        ]);
    }
}
