<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ContentExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ContentExportController extends Controller
{
    public function __invoke(Request $request, ContentExportService $exporter): JsonResponse
    {
        $payload = Cache::remember(
            'content-export:v2',
            now()->addMinute(),
            fn (): array => $exporter->build(),
        );
        $etag = '"'.hash('sha256', json_encode($payload)).'"';

        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304)->header('ETag', $etag);
        }

        return response()
            ->json($payload)
            ->header('ETag', $etag)
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }
}
