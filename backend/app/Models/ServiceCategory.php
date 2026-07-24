<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\HasManagedContent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceCategory extends Model
{
    use HasFactory, HasManagedContent, SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'summary', 'content_blocks', 'status', 'published_at',
        'legacy_path', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'content_blocks' => 'array',
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function routePath(): string
    {
        return "/services/category/{$this->slug}/";
    }
}
