<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ImageProcessor
{
    public function import(string $absoluteSource, string $relativeSource): Media
    {
        $existing = Media::query()->where('source_path', $relativeSource)->first();
        $sha256 = hash_file('sha256', $absoluteSource);

        if ($existing && hash_equals($existing->sha256, $sha256)) {
            return $existing;
        }

        $metadata = $this->inspect($absoluteSource);
        $extension = config("media.allowed_mimes.{$metadata['mime']}");
        $cleanRelative = ltrim(str_replace('\\', '/', $relativeSource), '/');
        $path = 'legacy/'.$this->replaceExtension($cleanRelative, $extension);

        return $this->persist(
            source: $absoluteSource,
            path: $path,
            originalName: basename($relativeSource),
            sourcePath: $cleanRelative,
            metadata: $metadata,
            sha256: $sha256,
        );
    }

    public function upload(UploadedFile $upload, ?int $userId = null): Media
    {
        if (! $upload->isValid()) {
            throw ValidationException::withMessages([
                'image' => 'فشل استلام الصورة.',
            ]);
        }

        $metadata = $this->inspect($upload->getRealPath());
        $extension = config("media.allowed_mimes.{$metadata['mime']}");
        $path = sprintf(
            'media/%s/%s.%s',
            now()->format('Y/m'),
            Str::uuid(),
            $extension,
        );

        return $this->persist(
            source: $upload->getRealPath(),
            path: $path,
            originalName: $upload->getClientOriginalName(),
            sourcePath: null,
            metadata: $metadata,
            sha256: hash_file('sha256', $upload->getRealPath()),
            userId: $userId,
        );
    }

    /**
     * @return array{mime: string, size: int, width: int, height: int}
     */
    private function inspect(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw ValidationException::withMessages(['image' => 'ملف الصورة غير قابل للقراءة.']);
        }

        $size = filesize($path);
        if ($size === false || $size <= 0 || $size > config('media.max_bytes')) {
            throw ValidationException::withMessages([
                'image' => 'حجم الصورة غير مسموح.',
            ]);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! array_key_exists($mime, config('media.allowed_mimes'))) {
            throw ValidationException::withMessages([
                'image' => 'نوع الصورة غير مسموح. الأنواع المتاحة: JPEG وPNG وWebP.',
            ]);
        }

        $dimensions = @getimagesize($path);
        if (! $dimensions) {
            throw ValidationException::withMessages(['image' => 'الصورة تالفة أو غير قابلة للمعالجة.']);
        }

        [$width, $height] = $dimensions;
        if (
            $width > config('media.max_width') ||
            $height > config('media.max_height') ||
            ($width * $height) > config('media.max_pixels')
        ) {
            throw ValidationException::withMessages([
                'image' => 'أبعاد الصورة تتجاوز الحدود الآمنة.',
            ]);
        }

        return compact('mime', 'size', 'width', 'height');
    }

    /**
     * @param  array{mime: string, size: int, width: int, height: int}  $metadata
     */
    private function persist(
        string $source,
        string $path,
        string $originalName,
        ?string $sourcePath,
        array $metadata,
        string $sha256,
        ?int $userId = null,
    ): Media {
        $diskName = config('media.disk');
        $disk = Storage::disk($diskName);
        $stream = fopen($source, 'rb');

        if ($stream === false || ! $disk->put($path, $stream)) {
            throw new RuntimeException('Failed to persist the validated image.');
        }
        if (is_resource($stream)) {
            fclose($stream);
        }

        $webpPath = $this->replaceExtension($path, 'webp');
        if ($metadata['mime'] === 'image/webp') {
            $webpPath = $path;
        } else {
            $this->generateWebp(
                $disk->path($path),
                $disk->path($webpPath),
                $metadata['mime'],
            );
        }

        return Media::updateOrCreate(
            $sourcePath ? ['source_path' => $sourcePath] : ['path' => $path],
            [
                'disk' => $diskName,
                'path' => $path,
                'webp_path' => $webpPath,
                'source_path' => $sourcePath,
                'original_name' => $originalName,
                'mime_type' => $metadata['mime'],
                'size' => $metadata['size'],
                'width' => $metadata['width'],
                'height' => $metadata['height'],
                'sha256' => $sha256,
                'status' => 'ready',
                'uploaded_by' => $userId,
            ],
        );
    }

    private function generateWebp(
        string $source,
        string $destination,
        string $mime,
    ): void {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/png' => @imagecreatefrompng($source),
            default => false,
        };

        if (! $image) {
            throw new RuntimeException('Validated image could not be decoded.');
        }

        if ($mime === 'image/png') {
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
        }

        if (! imagewebp($image, $destination, config('media.webp_quality'))) {
            imagedestroy($image);
            throw new RuntimeException('Could not generate the WebP derivative.');
        }

        imagedestroy($image);
    }

    private function replaceExtension(string $path, string $extension): string
    {
        return preg_replace('/\.[^.\/]+$/', ".{$extension}", $path) ?? "{$path}.{$extension}";
    }
}
