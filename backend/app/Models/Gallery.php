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
