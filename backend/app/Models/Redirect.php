<?php

namespace App\Models;

use App\Models\Concerns\InvalidatesContentExport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Redirect extends Model
{
    use InvalidatesContentExport;

    protected $fillable = [
        'from_path', 'to_path', 'status_code', 'reason', 'is_active', 'created_by',
        'hit_count', 'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_active' => 'boolean',
            'hit_count' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $redirect): void {
            Validator::make($redirect->getAttributes(), [
                'from_path' => ['required', 'string', 'max:255', 'starts_with:/'],
                'to_path' => ['required', 'string', 'max:255', 'starts_with:/'],
                'status_code' => ['required', Rule::in([301, 302])],
            ])->validate();

            if ($redirect->from_path === $redirect->to_path) {
                throw ValidationException::withMessages([
                    'to_path' => 'لا يمكن تحويل المسار إلى نفسه.',
                ]);
            }

            $cursor = $redirect->to_path;

            for ($depth = 0; $depth < 20; $depth++) {
                if ($cursor === $redirect->from_path) {
                    throw ValidationException::withMessages([
                        'to_path' => 'هذا التحويل ينشئ حلقة تحويل غير مسموحة.',
                    ]);
                }

                $next = self::query()
                    ->where('from_path', $cursor)
                    ->where('is_active', true)
                    ->when($redirect->exists, fn ($query) => $query->whereKeyNot($redirect->getKey()))
                    ->value('to_path');

                if (! $next) {
                    break;
                }

                $cursor = $next;
            }
        });
    }
}
