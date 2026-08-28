<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Validation\ValidationException;

class SeoMeta extends Model
{
    protected $table = 'seo_meta';

    protected $fillable = [
        'title', 'description', 'canonical', 'robots', 'open_graph', 'twitter',
        'keywords', 'hreflang', 'json_ld', 'json_ld_sha256',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'open_graph' => 'array',
            'twitter' => 'array',
            'hreflang' => 'array',
            'json_ld' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $seo): void {
            if ($seo->isDirty('json_ld') || blank($seo->json_ld_sha256)) {
                $seo->json_ld_sha256 = hash(
                    'sha256',
                    json_encode(
                        $seo->getAttribute('json_ld') ?? [],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                    ) ?: '[]',
                );
            }
        });

        static::updating(function (self $seo): void {
            if (! $seo->isDirty('canonical')) {
                return;
            }

            $hasProtectedRoute = RouteRecord::query()
                ->where('routable_type', $seo->getAttribute('seoable_type'))
                ->where('routable_id', $seo->getAttribute('seoable_id'))
                ->where('is_legacy', true)
                ->exists();

            if ($hasProtectedRoute) {
                throw ValidationException::withMessages([
                    'canonical' => 'Canonical للرابط المستعاد محمي ولا يمكن تغييره.',
                ]);
            }
        });
    }

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }
}
