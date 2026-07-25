<?php

namespace App\Services;

use App\Contracts\ManagedContent;
use App\Models\ActivityLog;
use App\Models\RouteRecord;
use App\Models\SitemapEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContentLifecycleService
{
    public function archive(Model $content, ?int $actorId = null): void
    {
        if (! $content instanceof ManagedContent) {
            return;
        }

        DB::transaction(function () use ($content, $actorId): void {
            $route = $content->routeRecord()->lockForUpdate()->first();

            if ($route instanceof RouteRecord) {
                $route->update(['is_published' => false]);

                SitemapEntry::query()
                    ->where('route_registry_id', $route->getKey())
                    ->where('is_included', true)
                    ->update([
                        'is_included' => false,
                        'lastmod' => now()->toDateString(),
                        'updated_at' => now(),
                    ]);
            }

            $this->audit(
                action: 'content.archived',
                content: $content,
                actorId: $actorId,
                after: [
                    'path' => $route?->getAttribute('path'),
                    'status' => 'archived',
                ],
            );
        }, 3);

        Cache::forget(ContentExportService::CACHE_KEY);
        app(PublishPipeline::class)->queue($content, $actorId);
    }

    public function restore(Model $content, ?int $actorId = null): void
    {
        if (! $content instanceof ManagedContent) {
            return;
        }

        app(ContentPublishingService::class)->sync($content, $actorId);
    }

    /**
     * @param  array<string, mixed>  $after
     */
    private function audit(
        string $action,
        Model $content,
        ?int $actorId,
        array $after,
    ): void {
        ActivityLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'subject_type' => $content->getMorphClass(),
            'subject_id' => $content->getKey(),
            'after' => $after,
            'ip_address' => request()?->ip(),
            'user_agent' => Str::limit((string) request()?->userAgent(), 500, ''),
        ]);
    }
}
