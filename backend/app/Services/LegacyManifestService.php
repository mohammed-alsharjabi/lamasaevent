<?php

namespace App\Services;

use RuntimeException;

class LegacyManifestService
{
    /**
     * @return array{
     *   legacy_root: string,
     *   manifest_path: string,
     *   manifest: array<string, mixed>,
     *   routes: list<array<string, mixed>>
     * }
     */
    public function load(?string $legacyOption = null, ?string $manifestOption = null): array
    {
        $legacyRoot = realpath($legacyOption ?: (string) config('recovery.legacy_dist'));
        $manifestPath = realpath(
            $manifestOption ?: (string) config('recovery.manifest'),
        );

        if (! $legacyRoot || ! is_dir($legacyRoot)) {
            throw new RuntimeException('Pass a readable immutable legacy directory with --legacy.');
        }

        if (! $manifestPath || ! is_file($manifestPath)) {
            throw new RuntimeException('route-manifest.json was not found.');
        }

        $manifest = json_decode(
            file_get_contents($manifestPath),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $routes = $manifest['routes'] ?? [];
        $sitemapPath = $legacyRoot.DIRECTORY_SEPARATOR.'sitemap.xml';

        if (! is_file($sitemapPath)) {
            throw new RuntimeException('The immutable sitemap.xml was not found.');
        }

        if (! hash_equals(
            (string) ($manifest['legacy']['sitemap_sha256'] ?? ''),
            hash_file('sha256', $sitemapPath),
        )) {
            throw new RuntimeException('legacy-dist/sitemap.xml changed after the recovery audit.');
        }

        if (count($routes) !== (int) ($manifest['legacy']['route_count'] ?? -1)) {
            throw new RuntimeException('Route manifest count is inconsistent.');
        }

        foreach ($routes as $route) {
            $htmlPath = $this->safePath($legacyRoot, $route['legacy_file']);
            $html = file_get_contents($htmlPath);

            if (! hash_equals($route['legacy_html_sha256'], hash('sha256', $html))) {
                throw new RuntimeException("Legacy HTML changed: {$route['legacy_file']}");
            }
        }

        return compact('legacyRoot', 'manifestPath', 'manifest', 'routes') + [
            'legacy_root' => $legacyRoot,
            'manifest_path' => $manifestPath,
        ];
    }

    public function safePath(string $legacyRoot, string $relative): string
    {
        $candidate = realpath($legacyRoot.DIRECTORY_SEPARATOR.ltrim($relative, '/'));
        $prefix = rtrim($legacyRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! $candidate || ! str_starts_with($candidate, $prefix) || ! is_file($candidate)) {
            throw new RuntimeException("Unsafe or missing legacy path: {$relative}");
        }

        return $candidate;
    }
}
