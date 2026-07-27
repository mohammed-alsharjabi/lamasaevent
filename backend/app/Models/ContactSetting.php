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

    protected static function booted(): void
    {
        static::saving(function (self $contact): void {
            $socialLinks = $contact->getAttribute('social_links');

            if (is_array($socialLinks)) {
                $contact->setAttribute(
                    'social_links',
                    array_filter(
                        array_map(
                            fn (mixed $url): string => trim((string) $url),
                            $socialLinks,
                        ),
                        fn (string $url): bool => $url !== '',
                    ),
                );
            }

            if (filled($contact->getAttribute('whatsapp'))) {
                $contact->setAttribute(
                    'whatsapp',
                    preg_replace(
                        '/\D+/',
                        '',
                        (string) $contact->getAttribute('whatsapp'),
                    ),
                );
            }

            if (
                filled($contact->getAttribute('phone'))
                && (
                    blank($contact->getAttribute('phone_display'))
                    || (
                        $contact->isDirty('phone')
                        && ! $contact->isDirty('phone_display')
                    )
                )
            ) {
                $contact->setAttribute(
                    'phone_display',
                    self::formatPhoneForDisplay((string) $contact->getAttribute('phone')),
                );
            }
        });
    }

    public static function formatPhoneForDisplay(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '966') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 3);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '05')) {
            return substr($digits, 0, 3)
                .' '.substr($digits, 3, 3)
                .' '.substr($digits, 6, 4);
        }

        return trim($phone);
    }
}
