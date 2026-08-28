<?php

namespace App\Console\Commands;

use App\Services\LegacyManifestService;
use App\Services\LegacyVerificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class VerifyLegacyImport extends Command
{
    protected $signature = 'legacy:verify
        {--legacy= : Absolute path to the immutable legacy build}
        {--manifest= : Path to route-manifest.json}';

    protected $description = 'Verify imported routes, sitemap URLs, and SEO against the immutable manifest';

    public function handle(
        LegacyManifestService $manifests,
        LegacyVerificationService $verification,
    ): int {
        try {
            $inspection = $manifests->load(
                $this->option('legacy'),
                $this->option('manifest'),
            );
            $report = $verification->verify($inspection['routes']);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        Storage::disk('local')->put(
            'recovery/legacy-verification.json',
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        $this->table([
            'Expected routes', 'Imported routes', 'Legacy sitemap URLs', 'SEO mismatches',
        ], [[
            $report['expected_route_count'],
            $report['actual_legacy_route_count'],
            $report['actual_sitemap_url_count'],
            count($report['seo_mismatches']),
        ]]);

        $report['passed']
            ? $this->info('Legacy verification passed.')
            : $this->error('Legacy verification failed. See storage/app/recovery/legacy-verification.json.');

        return $report['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
