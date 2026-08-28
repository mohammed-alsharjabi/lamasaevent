<?php

namespace App\Models;

use App\Models\Concerns\InvalidatesContentExport;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use InvalidatesContentExport;

    protected $table = 'settings';

    protected $fillable = [
        'key', 'value', 'group', 'is_public', 'is_sensitive', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_public' => 'boolean',
            'is_sensitive' => 'boolean',
        ];
    }
}
