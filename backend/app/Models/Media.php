<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Media extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'media';

    protected $fillable = [
        'disk', 'path', 'webp_path', 'source_path', 'original_name', 'mime_type',
        'size', 'width', 'height', 'sha256', 'alt', 'caption', 'status',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }

    public function galleries(): BelongsToMany
    {
        return $this->belongsToMany(Gallery::class)
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }
}
