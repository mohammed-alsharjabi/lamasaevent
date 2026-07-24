<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SitemapEntry;
use Illuminate\Http\Response;

class SitemapXmlController extends Controller
{
    public function __invoke(): Response
    {
        $entries = SitemapEntry::query()
            ->where('is_included', true)
            ->orderBy('position')
            ->get();

        return response()
            ->view('sitemap', compact('entries'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
