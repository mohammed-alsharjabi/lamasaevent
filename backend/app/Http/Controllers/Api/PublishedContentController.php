<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Article;
use App\Models\Gallery;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublishedContentController extends Controller
{
    public function index(Request $request, string $type): JsonResponse
    {
        $model = $this->modelFor($type);
        $records = $model::query()
            ->where('status', 'published')
            ->with($this->relationsFor($type))
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json($records)
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }

    public function show(string $type, int $id): JsonResponse
    {
        $model = $this->modelFor($type);
        $record = $model::query()
            ->where('status', 'published')
            ->with($this->relationsFor($type))
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
            'galleries' => ['items.media'],
            'services' => [
                'seoMeta',
                'faqs',
                'media',
                'category',
                'parent:id,title,slug',
                'children' => fn ($query) => $query
                    ->where('status', 'published')
                    ->select(['id', 'parent_id', 'title', 'slug', 'sort_order']),
            ],
            default => ['seoMeta', 'faqs', 'media'],
        };
    }
}
