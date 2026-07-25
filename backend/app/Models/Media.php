<?php

namespace App\Models;

use App\Models\Concerns\InvalidatesContentExport;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory, InvalidatesContentExport, SoftDeletes;

    protected $table = 'media';

    protected $appends = ['public_url'];

    protected $fillable = [
        'disk', 'path', 'webp_path', 'source_path', 'original_name', 'mime_type',
        'size', 'width', 'height', 'sha256', 'alt', 'caption', 'status',
        'uploaded_by', 'avif_path', 'variants', 'title',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'variants' => 'array',
        ];
    }

    protected function publicUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->url());
    }

    public function url(): string
    {
        return Storage::disk($this->disk)
            ->url($this->webp_path ?: $this->path);
    }

    public function galleries(): BelongsToMany
    {
        return $this->belongsToMany(Gallery::class)
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }
}
