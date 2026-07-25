<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id', 'action', 'subject_type', 'subject_id', 'before', 'after',
        'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }
}
