<?php

namespace App\Services;

use App\Contracts\ManagedContent;
use App\Enums\ContentStatus;
use App\Models\ActivityLog;
use App\Models\RouteRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContentPublishingService
{
    public function sync(Model $content, ?int $actorId = null): void
    {
        if (! $content instanceof ManagedContent) {
            return;
        }

        DB::transaction(function () use ($content, $actorId): void {
            $status = $content->getAttribute('status');

            if (! $status instanceof ContentStatus) {
                return;
            }

            if (
                $status === ContentStatus::Published
                && ! $content->getAttribute('published_at')
            ) {
                $content->forceFill(['published_at' => now()])->saveQuietly();
            }

            $published = $status === ContentStatus::Published;
            $path = $content->routePath();
            $route = $content->routeRecord()->updateOrCreate([], [
                'path' => $path,
                'exact_url' => rtrim(config('app.production_url'), '/').$path,
                'is_legacy' => filled($content->getAttribute('legacy_path')),
                'slug_locked' => $published,
                'is_published' => $published,
                'published_at' => $content->getAttribute('published_at'),
            ]);

            if (! $route instanceof RouteRecord) {
                return;
            }

            ActivityLog::create([
                'user_id' => $actorId,
                'action' => $published ? 'content.published' : 'content.saved_as_draft',
                'subject_type' => $content->getMorphClass(),
                'subject_id' => $content->getKey(),
                'after' => [
                    'path' => $route->getAttribute('path'),
                    'status' => $status->value,
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => Str::limit((string) request()?->userAgent(), 500, ''),
            ]);
        });

        Cache::forget('content-export:v2');
        app(PublishPipeline::class)->queue($content, $actorId);
    }
}
