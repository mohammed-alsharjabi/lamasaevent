<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContentPublishingService
{
    public function sync(Model $content, ?int $actorId = null): void
    {
        if (! method_exists($content, 'routePath')) {
            return;
        }

        DB::transaction(function () use ($content, $actorId): void {
            if ($content->status === ContentStatus::Published && ! $content->published_at) {
                $content->forceFill(['published_at' => now()])->saveQuietly();
            }

            $published = $content->status === ContentStatus::Published;
            $path = $content->routePath();
            $route = $content->routeRecord()->updateOrCreate([], [
                'path' => $path,
                'exact_url' => rtrim(config('app.production_url'), '/').$path,
                'is_legacy' => filled($content->legacy_path),
                'slug_locked' => $published,
                'is_published' => $published,
                'published_at' => $content->published_at,
            ]);

            ActivityLog::create([
                'user_id' => $actorId,
                'action' => $published ? 'content.published' : 'content.saved_as_draft',
                'subject_type' => $content->getMorphClass(),
                'subject_id' => $content->getKey(),
                'after' => [
                    'path' => $route->path,
                    'status' => $content->status->value,
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => Str::limit((string) request()?->userAgent(), 500, ''),
            ]);
        });
    }
}
