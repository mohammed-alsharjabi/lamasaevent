<?php

namespace App\Models\Concerns;

use App\Enums\ContentStatus;
use App\Models\Media;
use App\Models\RouteRecord;
use App\Models\SeoMeta;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Validator;

trait HasManagedContent
{
    public bool $allowPublishedSlugChange = false;

    protected static function bootHasManagedContent(): void
    {
        static::updating(function (self $model): void {
            if (
                $model->isDirty('slug') &&
                $model->getRawOriginal('status') === ContentStatus::Published->value &&
                ! $model->allowPublishedSlugChange
            ) {
                Validator::make([], [])->after(function ($validator): void {
                    $validator->errors()->add(
                        'slug',
                        'لا يمكن تعديل الرابط بعد النشر دون إنشاء تحويل 301.',
                    );
                })->validate();
            }
        });
    }

    public function seoMeta(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    public function routeRecord(): MorphOne
    {
        return $this->morphOne(RouteRecord::class, 'routable');
    }

    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediaable', 'mediaables')
            ->withPivot(['role', 'sort_order'])
            ->orderByPivot('sort_order');
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published;
    }

    abstract public function routePath(): string;
}
