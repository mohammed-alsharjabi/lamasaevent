<?php

return [
    'disk' => env('MEDIA_DISK', 'public'),
    'max_bytes' => (int) env('MEDIA_MAX_BYTES', 8 * 1024 * 1024),
    'max_width' => (int) env('MEDIA_MAX_WIDTH', 12000),
    'max_height' => (int) env('MEDIA_MAX_HEIGHT', 12000),
    'max_pixels' => (int) env('MEDIA_MAX_PIXELS', 40_000_000),
    'webp_quality' => (int) env('MEDIA_WEBP_QUALITY', 82),
    'allowed_mimes' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ],
];
