<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ContentExportService;
use App\Services\ContentSnapshotWriter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class ContentExportController extends Controller
{
    public function __invoke(
        Request $request,
        ContentExportService $exporter,
        ContentSnapshotWriter $writer,
    ): Response {
        $json = Cache::remember(
            ContentExportService::CACHE_KEY,
            now()->addMinute(),
            fn (): string => $writer->encode($exporter->build()),
        );
        $etag = '"'.hash('sha256', $json).'"';

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        return response($json)
            ->header('Content-Type', 'application/json; charset=UTF-8')
            ->header('ETag', $etag)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
