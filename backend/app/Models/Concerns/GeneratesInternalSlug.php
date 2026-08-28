<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait GeneratesInternalSlug
{
    protected static function bootGeneratesInternalSlug(): void
    {
        static::creating(function (Model $model): void {
            if (filled($model->getAttribute('slug'))) {
                return;
            }

            $source = (string) (
                $model->getAttribute('title')
                ?: $model->getAttribute('name')
                ?: class_basename($model)
            );
            $base = Str::slug($source);

            if ($base === '') {
                $base = Str::kebab(class_basename($model));
            }

            $base = Str::limit($base, 160, '');
            $candidate = $base;
            $suffix = 2;

            while (
                $model->newQueryWithoutScopes()
                    ->where('slug', $candidate)
                    ->exists()
            ) {
                $candidate = Str::limit($base, 150, '').'-'.$suffix;
                $suffix++;
            }

            $model->setAttribute('slug', $candidate);
        });
    }
}
