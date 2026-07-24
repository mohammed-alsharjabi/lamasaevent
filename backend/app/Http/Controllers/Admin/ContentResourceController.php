<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ServiceCategory;
use App\Services\ContentPublishingService;
use App\Services\SlugRedirectService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ContentResourceController extends Controller
{
    public function __construct(
        private readonly ContentPublishingService $publishing,
        private readonly SlugRedirectService $slugRedirects,
    ) {}

    public function index(string $resource): View
    {
        $config = $this->config($resource);

        return view('admin.resources.index', [
            'resource' => $resource,
            'config' => $config,
            'records' => $config['model']::query()->latest('updated_at')->paginate(30),
        ]);
    }

    public function create(string $resource): View
    {
        $config = $this->config($resource);

        return $this->formView($resource, $config, new $config['model']);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $config = $this->config($resource);
        $model = new $config['model'];
        $data = $this->validated($request, $resource, $config, $model);

        DB::transaction(function () use ($request, $model, $data): void {
            $model->fill(Arr::except($data, ['seo']));
            $model->save();
            $this->syncSeo($model, $data['seo'] ?? null);
            $this->publishing->sync($model, $request->user()->id);
        });

        return redirect()
            ->route('admin.resources.edit', [$resource, $model])
            ->with('success', "تم إنشاء {$config['singular']}.");
    }

    public function edit(string $resource, int $record): View
    {
        $config = $this->config($resource);
        $model = $config['model']::query()->with('seoMeta')->findOrFail($record);

        return $this->formView($resource, $config, $model);
    }

    public function update(
        Request $request,
        string $resource,
        int $record,
    ): RedirectResponse {
        $config = $this->config($resource);
        $model = $config['model']::query()->with('seoMeta')->findOrFail($record);
        $data = $this->validated($request, $resource, $config, $model);
        $newSlug = $data['slug'] ?? null;
        $slugChanged = $newSlug !== null && $newSlug !== $model->slug;

        if (
            $slugChanged &&
            $model->status === ContentStatus::Published &&
            ! $request->boolean('create_redirect')
        ) {
            throw ValidationException::withMessages([
                'slug' => 'أكد إنشاء Redirect 301 لتغيير slug بعد النشر.',
            ]);
        }

        DB::transaction(function () use (
            $request,
            $model,
            $data,
            $slugChanged,
            $newSlug,
        ): void {
            $before = $model->toArray();
            $model->fill(Arr::except($data, ['seo', 'slug']));
            $model->save();

            if ($slugChanged) {
                if ($model->status === ContentStatus::Published) {
                    $this->slugRedirects->change($model, $newSlug, $request->user());
                } else {
                    $model->slug = $newSlug;
                    $model->save();
                }
            }

            $this->syncSeo($model, $data['seo'] ?? null);
            $this->publishing->sync($model, $request->user()->id);

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'content.updated',
                'subject_type' => $model->getMorphClass(),
                'subject_id' => $model->getKey(),
                'before' => $before,
                'after' => $model->fresh()->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            ]);
        });

        return back()->with('success', "تم تحديث {$config['singular']}.");
    }

    public function destroy(
        Request $request,
        string $resource,
        int $record,
    ): RedirectResponse {
        $config = $this->config($resource);
        $model = $config['model']::query()->findOrFail($record);

        if ($model->status === ContentStatus::Published) {
            throw ValidationException::withMessages([
                'delete' => 'حوّل المحتوى إلى مسودة قبل حذفه.',
            ]);
        }

        $before = $model->toArray();
        $model->delete();
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'content.deleted',
            'subject_type' => $model->getMorphClass(),
            'subject_id' => $model->getKey(),
            'before' => $before,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);

        return redirect()
            ->route('admin.resources.index', $resource)
            ->with('success', "تم حذف {$config['singular']}.");
    }

    private function config(string $resource): array
    {
        $config = config("content.resources.{$resource}");
        abort_unless($config, 404);

        return $config;
    }

    private function formView(
        string $resource,
        array $config,
        Model $model,
    ): View {
        return view('admin.resources.form', [
            'resource' => $resource,
            'config' => $config,
            'record' => $model,
            'categories' => ServiceCategory::query()->orderBy('sort_order')->get(),
        ]);
    }

    private function validated(
        Request $request,
        string $resource,
        array $config,
        Model $model,
    ): array {
        $table = $model->getTable();
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            'published_at' => ['nullable', 'date'],
        ];

        foreach ($config['fields'] as $field) {
            $rules[$field] = match ($field) {
                'slug' => [
                    'required', 'string', 'max:180',
                    'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                    Rule::unique($table, 'slug')->ignore($model->getKey()),
                ],
                'path' => [
                    'required', 'string', 'max:255', 'starts_with:/',
                    Rule::unique($table, 'path')->ignore($model->getKey()),
                ],
                'type', 'topic' => ['nullable', 'string', 'max:100'],
                'excerpt', 'summary', 'description' => ['nullable', 'string', 'max:5000'],
                'content_blocks' => ['nullable', 'json'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
                'service_category_id' => ['nullable', 'exists:service_categories,id'],
                default => ['nullable'],
            };
        }

        if ($config['seo']) {
            $rules += [
                'seo.title' => ['required', 'string', 'max:255'],
                'seo.description' => ['required', 'string', 'max:5000'],
                'seo.canonical' => ['required', 'url:http,https', 'max:2048'],
                'seo.robots' => ['nullable', 'string', 'max:255'],
                'seo.open_graph' => ['nullable', 'json'],
                'seo.twitter' => ['nullable', 'json'],
                'seo.json_ld' => ['nullable', 'json'],
            ];
        }

        $validated = $request->validate($rules);
        if (array_key_exists('content_blocks', $validated)) {
            $validated['content_blocks'] = $this->decodeJson(
                $validated['content_blocks'],
                'content_blocks',
            );
        }

        foreach (['open_graph', 'twitter', 'json_ld'] as $field) {
            if (isset($validated['seo'][$field])) {
                $validated['seo'][$field] = $this->decodeJson(
                    $validated['seo'][$field],
                    "seo.{$field}",
                );
            }
        }

        return $validated;
    }

    private function decodeJson(?string $value, string $field): ?array
    {
        if (blank($value)) {
            return null;
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([$field => 'JSON غير صالح.']);
        }

        return $decoded;
    }

    private function syncSeo(Model $model, ?array $seo): void
    {
        if ($seo === null || ! method_exists($model, 'seoMeta')) {
            return;
        }

        $seo['json_ld_sha256'] = hash(
            'sha256',
            json_encode($seo['json_ld'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
        $model->seoMeta()->updateOrCreate([], $seo);
    }
}
