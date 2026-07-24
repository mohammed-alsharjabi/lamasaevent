<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    protected $fillable = [
        'from_path', 'to_path', 'status_code', 'reason', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return ['status_code' => 'integer', 'is_active' => 'boolean'];
    }
}
