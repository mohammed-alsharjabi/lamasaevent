<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CairoFontController extends Controller
{
    public function __invoke(string $font): BinaryFileResponse
    {
        abort_unless(in_array($font, [
            'cairo-arabic.woff2',
            'cairo-latin.woff2',
        ], true), 404);

        $path = public_path('fonts/cairo/'.$font);

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'font/woff2',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
