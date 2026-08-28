<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PublishJob extends Model
{
    protected $fillable = [
        'publishable_type', 'publishable_id', 'action', 'status', 'scheduled_for',
        'started_at', 'finished_at', 'snapshot_path', 'build_version',
        'failure_reason', 'attempts', 'requested_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function publishable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
