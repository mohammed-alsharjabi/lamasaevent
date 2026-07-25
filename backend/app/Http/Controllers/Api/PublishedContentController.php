<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PublishedContentQueryRequest;
use App\Models\Area;
use App\Models\Article;
use App\Models\Gallery;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

class PublishedContentController extends Controller
{
    public function index(PublishedContentQueryRequest $request, string $type): JsonResponse
    {
        $model = $this->modelFor($type);
        $query = $model::query()
            ->where('status', 'published')
            ->with($this->relationsFor($type));

        if (in_array($type, ['service-categories', 'areas'], true)) {
            $query->where('is_active', true);
        }

        $records = $query
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json($records)
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }

    public function show(string $type, int $id): JsonResponse
    {
        $model = $this->modelFor($type);
        $query = $model::query()
            ->where('status', 'published')
            ->with($this->relationsFor($type));

        if (in_array($type, ['service-categories', 'areas'], true)) {
            $query->where('is_active', true);
        }

        $record = $query
            ->findOrFail($id);

        return response()->json(['data' => $record])
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }

    /**
     * @return class-string<Model>
     */
    private function modelFor(string $type): string
    {
        return match ($type) {
            'articles' => Article::class,
            'services' => Service::class,
            'service-categories' => ServiceCategory::class,
            'areas' => Area::class,
            'pages' => Page::class,
            'galleries' => Gallery::class,
            default => abort(404),
        };
    }

    /** @return array<int|string, mixed> */
    private function relationsFor(string $type): array
    {
        return match ($type) {
            'services' => [
                'seoMeta',
                'faqs' => fn ($query) => $query->where('is_active', true),
                'media',
                'heroMedia',
                'category',
                'parent:id,title,slug',
                'children' => fn ($query) => $query
                    ->where('status', 'published')
                    ->select(['id', 'parent_id', 'title', 'slug', 'sort_order']),
            ],
            'galleries' => [
                'items' => fn ($query) => $query->where('is_active', true),
                'items.media',
            ],
            default => [
                'seoMeta',
                'faqs' => fn ($query) => $query->where('is_active', true),
                'media',
                'heroMedia',
            ],
        };
    }
}
