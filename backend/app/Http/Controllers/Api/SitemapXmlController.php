<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SitemapEntry;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapXmlController extends Controller
{
    public function __invoke(): Response
    {
        $entries = Cache::remember(
            'sitemap-xml:v1',
            now()->addMinute(),
            fn () => SitemapEntry::query()
                ->where('is_included', true)
                ->orderBy('position')
                ->get(),
        );

        return response()
            ->view('sitemap', compact('entries'))
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }
}
