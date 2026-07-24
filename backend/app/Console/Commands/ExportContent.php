<?php

namespace App\Console\Commands;

use App\Services\ContentExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExportContent extends Command
{
    protected $signature = 'content:export
        {output? : JSON output path, defaults to ../frontend/src/data/content-export.json}';

    protected $description = 'Export published structured content for the Astro static build';

    public function handle(ContentExportService $exporter): int
    {
        $output = $this->argument('output')
            ?: base_path('../frontend/src/data/content-export.json');
        File::ensureDirectoryExists(dirname($output));

        $payload = $exporter->build();
        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR,
        ).PHP_EOL;
        $temporary = $output.'.tmp';

        if (file_put_contents($temporary, $json, LOCK_EX) === false) {
            throw new RuntimeException('Could not write the temporary content export.');
        }
        if (! rename($temporary, $output)) {
            @unlink($temporary);
            throw new RuntimeException('Could not atomically publish the content export.');
        }

        $this->info(sprintf(
            'Exported %d routes to %s',
            count($payload['routes']),
            realpath($output) ?: $output,
        ));

        return self::SUCCESS;
    }
}
