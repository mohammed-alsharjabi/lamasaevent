<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\HasManagedContent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use HasFactory, HasManagedContent, SoftDeletes;

    protected $fillable = [
        'type', 'title', 'path', 'summary', 'content_blocks', 'status',
        'published_at', 'legacy_path',
    ];

    protected function casts(): array
    {
        return [
            'content_blocks' => 'array',
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function routePath(): string
    {
        return $this->path;
    }
}
