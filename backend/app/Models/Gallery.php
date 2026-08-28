<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\GeneratesInternalSlug;
use App\Models\Concerns\InvalidatesContentExport;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Gallery extends Model
{
    use GeneratesInternalSlug, HasFactory, InvalidatesContentExport, SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'description', 'status', 'published_at', 'created_by',
        'updated_by', 'is_featured', 'is_legacy', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'is_legacy' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $gallery): void {
            if (
                $gallery->getAttribute('status') === ContentStatus::Published
                && ! $gallery->getAttribute('published_at')
            ) {
                $gallery->setAttribute('published_at', now());
            }
        });

        static::updating(function (self $gallery): void {
            if (
                $gallery->getRawOriginal('is_legacy')
                && (
                    $gallery->isDirty('slug')
                    || (
                        $gallery->isDirty('status')
                        && $gallery->getAttribute('status') !== ContentStatus::Published
                    )
                )
            ) {
                throw ValidationException::withMessages([
                    'gallery' => 'لا يمكن تغيير رابط أو إلغاء نشر معرض الاستعادة المحمي.',
                ]);
            }
        });

        static::deleting(function (self $gallery): void {
            if ($gallery->getAttribute('is_legacy')) {
                throw ValidationException::withMessages([
                    'gallery' => 'لا يمكن حذف معرض الاستعادة المحمي.',
                ]);
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(GalleryItem::class)->orderBy('sort_order');
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class)
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }
}
