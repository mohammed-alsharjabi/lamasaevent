<?php

namespace App\Models;

use App\Models\Concerns\InvalidatesContentExport;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class Media extends Model
{
    use HasFactory, InvalidatesContentExport, SoftDeletes;

    protected $table = 'media';

    protected $appends = ['public_url'];

    protected $fillable = [
        'disk', 'path', 'webp_path', 'source_path', 'original_name', 'mime_type',
        'size', 'width', 'height', 'sha256', 'alt', 'caption', 'status',
        'uploaded_by', 'avif_path', 'variants', 'title', 'upload_fingerprint',
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

    protected static function booted(): void
    {
        static::deleting(function (self $media): void {
            if (filled($media->source_path)) {
                throw ValidationException::withMessages([
                    'image' => 'لا يمكن حذف صورة مستعادة ومحمي مسارها القديم.',
                ]);
            }

            if ($media->isInUse()) {
                throw ValidationException::withMessages([
                    'image' => 'لا يمكن حذف صورة مستخدمة. أزل ارتباطها بالمحتوى أولًا.',
                ]);
            }

            if ($media->isForceDeleting()) {
                throw ValidationException::withMessages([
                    'image' => 'الحذف النهائي للملفات معطل؛ استخدم الأرشفة القابلة للاستعادة.',
                ]);
            }
        });
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

    public function isInUse(): bool
    {
        if (
            DB::table('gallery_items')->where('media_id', $this->getKey())->exists()
            || DB::table('gallery_media')->where('media_id', $this->getKey())->exists()
            || DB::table('mediaables')->where('media_id', $this->getKey())->exists()
        ) {
            return true;
        }

        foreach ([
            'articles',
            'article_categories',
            'services',
            'service_categories',
            'areas',
            'pages',
        ] as $table) {
            if (DB::table($table)->where('hero_media_id', $this->getKey())->exists()) {
                return true;
            }
        }

        return false;
    }
}
