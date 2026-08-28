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
        $serviceIds = [];

        if (is_array($blocks)) {
            foreach ($blocks as $block) {
                if (! is_array($block)) {
                    continue;
                }

                if (($block['type'] ?? null) === 'gallery') {
                    foreach ((array) ($block['media_ids'] ?? []) as $mediaId) {
                        $mediaIds[(int) $mediaId] = (int) $mediaId;
                    }
                }

                if (($block['type'] ?? null) === 'related_services') {
                    foreach ((array) ($block['service_ids'] ?? []) as $serviceId) {
                        $serviceIds[(int) $serviceId] = (int) $serviceId;
                    }
                }
            }
        }

        return view('admin.services.preview', [
            'service' => $service,
            'galleryMedia' => Media::query()->whereKey(array_values($mediaIds))->get()->keyBy('id'),
            'relatedServices' => Service::query()
                ->whereKey(array_values($serviceIds))
                ->get()
                ->keyBy('id'),
            'cta' => app(ServiceDefaultsService::class)->cta($service),
        ]);
    }
}
