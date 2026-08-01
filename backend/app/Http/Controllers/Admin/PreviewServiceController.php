<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Service;
use App\Services\ServiceDefaultsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class PreviewServiceController extends Controller
{
    public function __invoke(Service $service): View
    {
        Gate::authorize('view', $service);
        $service->load(['heroMedia', 'category', 'parent', 'faqs']);
        $blocks = $service->getAttribute('content_blocks');
        $mediaIds = [];

        if (is_array($blocks)) {
            foreach ($blocks as $block) {
                if (! is_array($block) || ($block['type'] ?? null) !== 'gallery') {
                    continue;
                }

                foreach ((array) ($block['media_ids'] ?? []) as $mediaId) {
                    $mediaIds[(int) $mediaId] = (int) $mediaId;
                }
            }
        }

        return view('admin.services.preview', [
            'service' => $service,
            'galleryMedia' => Media::query()->whereKey(array_values($mediaIds))->get()->keyBy('id'),
            'cta' => app(ServiceDefaultsService::class)->cta($service),
        ]);
    }
}
