<?php

namespace App\Models;

use App\Models\Concerns\GeneratesInternalSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class ArticleCategory extends Model
{
    use GeneratesInternalSlug, SoftDeletes;

    protected $fillable = [
        'hero_media_id', 'name', 'slug', 'description', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $category): void {
            if ($category->articles()->exists()) {
                throw ValidationException::withMessages([
                    'delete' => 'انقل المقالات المرتبطة قبل حذف التصنيف.',
                ]);
            }
        });
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
