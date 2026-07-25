<?php

namespace App\Models\Concerns;

use App\Enums\ContentStatus;
use App\Models\Media;
use App\Models\ContentRevision;
use App\Models\Faq;
use App\Models\RouteRecord;
use App\Models\SeoMeta;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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

            $changed = array_diff(array_keys($model->getDirty()), ['updated_at']);

            if ($changed === []) {
                return;
            }

            $revision = ContentRevision::whereMorphedTo('revisionable', $model)->max('revision');

            ContentRevision::create([
                'revisionable_type' => $model->getMorphClass(),
                'revisionable_id' => $model->getKey(),
                'revision' => ((int) $revision) + 1,
                'snapshot' => $model->getOriginal(),
                'reason' => 'تعديل من لوحة التحكم',
                'created_by' => auth()->id(),
            ]);
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

    public function faqs(): MorphMany
    {
        return $this->morphMany(Faq::class, 'faqable')->orderBy('sort_order');
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(ContentRevision::class, 'revisionable')
            ->latest('revision');
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published;
    }

    abstract public function routePath(): string;
}
