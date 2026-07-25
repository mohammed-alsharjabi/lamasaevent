<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class ContentSnapshotWriter
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function encode(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR,
        );
    }

    public function write(string $output, string $json): void
    {
        File::ensureDirectoryExists(dirname($output));
        $temporary = $output.'.tmp';

        if (file_put_contents($temporary, $json.PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Could not write the temporary content snapshot.');
        }

        if (! rename($temporary, $output)) {
            @unlink($temporary);

            throw new RuntimeException('Could not atomically publish the content snapshot.');
        }
    }
}
