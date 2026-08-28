<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

interface ManagedContent
{
    public function routePath(): string;

    public function isPublished(): bool;

    public function seoMeta(): MorphOne;

    public function routeRecord(): MorphOne;

    public function faqs(): MorphMany;

    public function media(): MorphToMany;
}
