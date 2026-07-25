<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentRevision extends Model
{
    protected $fillable = [
        'revisionable_type', 'revisionable_id', 'revision', 'snapshot', 'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['revision' => 'integer', 'snapshot' => 'array'];
    }

    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
