<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\ActivityLog;
use App\Models\Redirect;
use App\Models\RouteRecord;
use App\Models\SitemapEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SlugRedirectService
{
    public function change(Model $content, string $newSlug, User $actor): void
    {
        if (! property_exists($content, 'allowPublishedSlugChange')) {
            throw ValidationException::withMessages([
                'slug' => 'هذا النوع لا يدعم تغيير الرابط بهذه العملية.',
            ]);
        }

        Validator::make(['slug' => $newSlug], [
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ])->validate();

        DB::transaction(function () use ($content, $newSlug, $actor): void {
            $oldSlug = (string) $content->slug;
            $oldPath = $content->routePath();

            if ($oldSlug === $newSlug) {
                return;
            }

            $content->slug = $newSlug;
            $newPath = $content->routePath();
            $content->slug = $oldSlug;

            $currentRouteId = $content->routeRecord?->id;
            $routeCollision = RouteRecord::where('path', $newPath)
                ->when($currentRouteId, fn ($query) => $query->whereKeyNot($currentRouteId))
                ->exists();

            if ($routeCollision || Redirect::where('from_path', $newPath)->exists()) {
                throw ValidationException::withMessages([
                    'slug' => 'المسار الجديد مستخدم أو محجوز بواسطة تحويل سابق.',
                ]);
            }

            if ($content->status === ContentStatus::Published) {
                Redirect::updateOrCreate(
                    ['from_path' => $oldPath],
                    [
                        'to_path' => $newPath,
                        'status_code' => 301,
                        'reason' => 'تغيير slug بعد النشر',
                        'is_active' => true,
                        'created_by' => $actor->id,
                    ],
                );
            }

            $content->allowPublishedSlugChange = true;
            $content->slug = $newSlug;
            $content->save();
            $content->allowPublishedSlugChange = false;

            $productionUrl = rtrim(config('app.production_url'), '/').$newPath;
            $content->routeRecord()->update([
                'path' => $newPath,
                'exact_url' => $productionUrl,
            ]);

            if ($content->seoMeta) {
                $openGraph = $content->seoMeta->open_graph ?? [];
                $twitter = $content->seoMeta->twitter ?? [];
                $openGraph['url'] = $productionUrl;
                $content->seoMeta->update([
                    'canonical' => $productionUrl,
                    'open_graph' => $openGraph,
                    'twitter' => $twitter,
                ]);
            }

            SitemapEntry::where('path', $oldPath)->update([
                'path' => $newPath,
                'loc' => $productionUrl,
                'lastmod' => now()->toDateString(),
            ]);

            ActivityLog::create([
                'user_id' => $actor->id,
                'action' => 'content.slug_changed_with_301',
                'subject_type' => $content->getMorphClass(),
                'subject_id' => $content->getKey(),
                'before' => ['slug' => $oldSlug, 'path' => $oldPath],
                'after' => ['slug' => $newSlug, 'path' => $newPath],
                'ip_address' => request()?->ip(),
                'user_agent' => Str::limit((string) request()?->userAgent(), 500, ''),
            ]);
        });
    }
}
