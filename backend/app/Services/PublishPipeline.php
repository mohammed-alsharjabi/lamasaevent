<?php

namespace App\Services;

use App\Jobs\GenerateFrontendSnapshot;
use App\Models\PublishJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PublishPipeline
{
    public function queue(Model $content, ?int $actorId): PublishJob
    {
        return Cache::lock('publish-pipeline:snapshot', 10)->block(
            5,
            function () use ($content, $actorId): PublishJob {
                $pending = PublishJob::query()
                    ->where('action', 'snapshot')
                    ->where('status', 'pending')
                    ->latest('id')
                    ->first();

                if ($pending instanceof PublishJob) {
                    return $pending;
                }

                $job = PublishJob::create([
                    'publishable_type' => $content->getMorphClass(),
                    'publishable_id' => $content->getKey(),
                    'action' => 'snapshot',
                    'status' => 'pending',
                    'scheduled_for' => now(),
                    'requested_by' => $actorId,
                ]);

                GenerateFrontendSnapshot::dispatch($job->id)->afterCommit();

                return $job;
            },
        );
    }
}
