<?php

namespace App\Models;

use App\Models\Concerns\InvalidatesContentExport;
use Illuminate\Database\Eloquent\Model;

class ContactSetting extends Model
{
    use InvalidatesContentExport;

    protected $fillable = [
        'phone', 'phone_display', 'whatsapp', 'email', 'city', 'region',
        'country_code', 'social_links',
    ];

    protected function casts(): array
    {
        return ['social_links' => 'array'];
    }
}
