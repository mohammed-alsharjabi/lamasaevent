<?php

namespace App\Services;

use App\Jobs\GenerateFrontendSnapshot;
use App\Models\PublishJob;
use Illuminate\Database\Eloquent\Model;

class PublishPipeline
{
    public function queue(Model $content, ?int $actorId): PublishJob
    {
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
    }
}
