<?php

namespace App\Services;

use App\Contracts\ManagedContent;
use App\Models\RouteRecord;
use App\Models\SeoMeta;
use App\Models\SitemapEntry;
use Illuminate\Database\Eloquent\Model;

class LegacyVerificationService
{
    /**
     * @param  list<array<string, mixed>>  $routes
     * @return array<string, mixed>
     */
    public function verify(array $routes): array
    {
        $expectedPaths = collect($routes)->pluck('path')->sort()->values();
        $actualPaths = RouteRecord::query()
            ->where('is_legacy', true)
            ->pluck('path')
            ->sort()
            ->values();
        $expectedUrls = collect($routes)->pluck('url')->sort()->values();
        $actualUrls = SitemapEntry::query()
            ->where('is_included', true)
            ->pluck('loc')
            ->sort()
            ->values();
        $seoMismatches = [];

        foreach ($routes as $route) {
            $record = RouteRecord::query()
                ->with('routable.seoMeta')
                ->where('path', $route['path'])
                ->first();
            $routable = $record?->getRelation('routable');
            $seo = $routable instanceof Model && $routable instanceof ManagedContent
                ? $routable->getRelation('seoMeta')
                : null;

            if (
                ! $record ||
                ! $record->is_published ||
                ! $seo instanceof SeoMeta ||
                $seo->title !== $route['seo']['title'] ||
                $seo->description !== $route['seo']['meta_description'] ||
                $seo->canonical !== $route['seo']['canonical'] ||
                $seo->json_ld_sha256 !== $route['seo']['json_ld_sha256']
            ) {
                $seoMismatches[] = $route['path'];
            }
        }

        $missingPaths = $expectedPaths->diff($actualPaths)->values()->all();
        $extraPaths = $actualPaths->diff($expectedPaths)->values()->all();
        $missingSitemapUrls = $expectedUrls->diff($actualUrls)->values()->all();
        $extraSitemapUrls = $actualUrls->diff($expectedUrls)->values()->all();
        $passed = $missingPaths === []
            && $extraPaths === []
            && $missingSitemapUrls === []
            && $extraSitemapUrls === []
            && $seoMismatches === [];

        return [
            'passed' => $passed,
            'verified_at' => now()->toIso8601String(),
            'expected_route_count' => count($routes),
            'actual_legacy_route_count' => $actualPaths->count(),
            'actual_sitemap_url_count' => $actualUrls->count(),
            'missing_paths' => $missingPaths,
            'extra_paths' => $extraPaths,
            'missing_sitemap_urls' => $missingSitemapUrls,
            'extra_sitemap_urls' => $extraSitemapUrls,
            'seo_mismatches' => $seoMismatches,
        ];
    }
}
