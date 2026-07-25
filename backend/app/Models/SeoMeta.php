<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoMeta extends Model
{
    protected $table = 'seo_meta';

    protected $fillable = [
        'title', 'description', 'canonical', 'robots', 'open_graph', 'twitter',
        'keywords', 'hreflang', 'json_ld', 'json_ld_sha256',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'open_graph' => 'array',
            'twitter' => 'array',
            'hreflang' => 'array',
            'json_ld' => 'array',
        ];
    }

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }
}
