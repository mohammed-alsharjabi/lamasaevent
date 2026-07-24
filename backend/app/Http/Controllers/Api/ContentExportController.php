<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ContentExportService;
use Illuminate\Http\JsonResponse;

class ContentExportController extends Controller
{
    public function __invoke(ContentExportService $exporter): JsonResponse
    {
        $payload = $exporter->build();
        $etag = '"'.hash('sha256', json_encode($payload)).'"';

        return response()
            ->json($payload)
            ->header('ETag', $etag)
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }
}
