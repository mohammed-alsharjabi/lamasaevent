<?php

namespace App\Console\Commands;

use App\Services\LegacyHtmlParser;
use App\Services\LegacyManifestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InspectLegacySite extends Command
{
    protected $signature = 'legacy:inspect
        {--legacy= : Absolute path to the immutable legacy build}
        {--manifest= : Path to route-manifest.json}';

    protected $description = 'Inspect and checksum the immutable legacy build without database writes';

    public function handle(
        LegacyManifestService $manifests,
        LegacyHtmlParser $parser,
    ): int {
        try {
            $inspection = $manifests->load(
                $this->option('legacy'),
                $this->option('manifest'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $types = [];
        $imageReferences = 0;
        $faqCount = 0;

        foreach ($inspection['routes'] as $route) {
            $types[$route['type']] = ($types[$route['type']] ?? 0) + 1;
            $htmlPath = $manifests->safePath(
                $inspection['legacy_root'],
                $route['legacy_file'],
            );
            $parsed = $parser->parse(file_get_contents($htmlPath), $route['legacy_file']);
            $imageReferences += count($parsed['images']);
            $faqCount += count($parsed['faqs']);
        }

        ksort($types);
        $report = [
            'inspected_at' => now()->toIso8601String(),
            'legacy_root' => $inspection['legacy_root'],
            'manifest_path' => $inspection['manifest_path'],
            'sitemap_sha256' => $inspection['manifest']['legacy']['sitemap_sha256'],
            'route_count' => count($inspection['routes']),
            'page_types' => $types,
            'image_references' => $imageReferences,
            'faq_count' => $faqCount,
            'database_writes' => 0,
        ];

        Storage::disk('local')->put(
            'recovery/legacy-inspection.json',
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        $this->table(
            ['النوع', 'العدد'],
            collect($types)->map(fn (int $count, string $type) => [$type, $count]),
        );
        $this->info("Verified {$report['route_count']} routes; report saved to storage/app/recovery.");

        return self::SUCCESS;
    }
}
