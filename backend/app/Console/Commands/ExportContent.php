<?php

namespace App\Console\Commands;

use App\Services\ContentExportService;
use App\Services\ContentSnapshotWriter;
use Illuminate\Console\Command;

class ExportContent extends Command
{
    protected $signature = 'content:export
        {output? : JSON output path, defaults to ../frontend/src/data/content-export.json}';

    protected $description = 'Export published structured content for the Astro static build';

    public function handle(
        ContentExportService $exporter,
        ContentSnapshotWriter $writer,
    ): int {
        $output = $this->argument('output')
            ?: base_path('../frontend/src/data/content-export.json');
        $payload = $exporter->build();
        $writer->write($output, $writer->encode($payload));

        $this->info(sprintf(
            'Exported %d routes to %s',
            count($payload['routes']),
            realpath($output) ?: $output,
        ));

        return self::SUCCESS;
    }
}
