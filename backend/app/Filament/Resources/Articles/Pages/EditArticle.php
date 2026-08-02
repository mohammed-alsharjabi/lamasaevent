<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Enums\ContentStatus;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Concerns\HandlesManagedContent;
use App\Models\Article;
use App\Models\SeoMeta;
use App\Models\User;
use App\Services\ArticleDuplicationService;
use App\Services\ArticleFormDataMapper;
use App\Services\SlugRedirectService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EditArticle extends EditRecord
{
    use HandlesManagedContent {
        mutateFormDataBeforeSave as managedMutateFormDataBeforeSave;
    }

    protected static string $resource = ArticleResource::class;

    public string $autosaveMessage = 'الحفظ التلقائي للمسودة مفعّل';

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->articleRecord();
        $data['developer_mode'] = false;

        foreach ([
            'seo_overrides', 'uses_generated_defaults', 'seo_title_override',
            'meta_description_override', 'slug_override', 'canonical_override',
            'robots_override', 'og_title_override', 'og_description_override',
            'og_image_override', 'schema_override', 'target_search_phrase',
            'hero_alt_override',
        ] as $attribute) {
            $data[$attribute] = $record->getAttribute($attribute);
        }

        if (! $record->uses_generated_defaults) {
            $seo = $record->seoMeta()->first();
            $data['seo_overrides'] = $seo instanceof SeoMeta ? [
                'title' => $seo->getAttribute('title'),
                'description' => $seo->getAttribute('description'),
                'canonical' => $seo->getAttribute('canonical'),
                'robots' => $seo->getAttribute('robots'),
                'keywords' => $seo->getAttribute('keywords'),
                'open_graph' => $seo->getAttribute('open_graph'),
                'twitter' => $seo->getAttribute('twitter'),
                'hreflang' => $seo->getAttribute('hreflang'),
                'json_ld' => $seo->getAttribute('json_ld'),
            ] : [];
        }

        return app(ArticleFormDataMapper::class)->forForm($data, $record);
    }

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = app(ArticleFormDataMapper::class)->forStorage($data);
        $data = $this->managedMutateFormDataBeforeSave($data);
        // Keep restored legacy SEO/link metadata immutable unless the article is
        // explicitly migrated away from its legacy path.
        $data['uses_generated_defaults'] = blank($this->articleRecord()->legacy_path);

        return $data;
    }

    /** @return array<Action> */
    protected function getFormActions(): array
    {
        return [
            Action::make('saveDraft')
                ->label('حفظ كمسودة')
                ->icon('heroicon-o-document')
                ->color('gray')
                ->action('saveDraft'),
            Action::make('previewChanges')
                ->label('معاينة')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->action('previewChanges'),
            Action::make('publish')
                ->label('نشر')
                ->icon('heroicon-o-paper-airplane')
                ->action('publish')
                ->visible(fn (): bool => auth()->user()?->hasPermission('articles.publish') ?? false),
            Action::make('publishAndCreateAnother')
                ->label('نشر وإضافة مقال جديد')
                ->icon('heroicon-o-plus-circle')
                ->action('publishAndCreateAnother')
                ->visible(fn (): bool => auth()->user()?->hasPermission('articles.publish') ?? false),
            $this->getCancelFormAction()->label('إلغاء'),
        ];
    }

    public function saveDraft(): void
    {
        $this->data['status'] = ContentStatus::Draft->value;
        $this->save();
    }

    public function previewChanges(): void
    {
        $this->save(shouldRedirect: false);
        $this->redirect(route('admin.articles.preview', $this->articleRecord()));
    }

    public function publish(): void
    {
        $this->data['status'] = ContentStatus::Published->value;
        $this->save();
    }

    public function publishAndCreateAnother(): void
    {
        $this->data['status'] = ContentStatus::Published->value;
        $this->save(shouldRedirect: false);
        $this->redirect(ArticleResource::getUrl('create'));
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $requestedSlug = Str::slug((string) ($data['slug_override'] ?? ''));
        $wasPublished = $record->getRawOriginal('status') === ContentStatus::Published->value;
        $record = parent::handleRecordUpdate($record, $data);

        if (
            $record instanceof Article
            && $wasPublished
            && $requestedSlug !== ''
            && $requestedSlug !== $record->slug
        ) {
            $actor = auth()->user();

            if (! $actor instanceof User) {
                abort(403);
            }

            app(SlugRedirectService::class)->change($record, $requestedSlug, $actor);
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewWebsite')
                ->label('معاينة قبل النشر')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn (): string => route('admin.articles.preview', $this->articleRecord()))
                ->openUrlInNewTab(),
            Action::make('duplicateArticle')
                ->label('تكرار المقال')
                ->icon('heroicon-o-square-2-stack')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('إنشاء نسخة مسودة من المقال؟')
                ->modalDescription('سيتم إنشاء رابط جديد تلقائيًا، ولن يتأثر المقال الأصلي.')
                ->action(function (): mixed {
                    $duplicate = app(ArticleDuplicationService::class)
                        ->duplicate($this->articleRecord(), auth()->id());

                    return redirect(ArticleResource::getUrl('edit', ['record' => $duplicate]));
                }),
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    public function autosaveDraft(): void
    {
        $record = $this->articleRecord();

        if ($record->status !== ContentStatus::Draft) {
            $this->autosaveMessage = 'تعديلات المقال المنشور تُحفظ يدويًا لحمايتها';

            return;
        }

        $currentHash = md5((string) str(
            json_encode($this->data, JSON_UNESCAPED_UNICODE),
        )->replace('\\', ''));

        if (isset($this->savedDataHash) && hash_equals($this->savedDataHash, $currentHash)) {
            return;
        }

        if (blank($this->data['title'] ?? null)) {
            $this->autosaveMessage = 'اكتب عنوان المقال ليبدأ الحفظ التلقائي';

            return;
        }

        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
        $this->autosaveMessage = 'حُفظت المسودة تلقائيًا عند '.now()->format('H:i');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'تم حفظ المقال وإرسال التحديث للموقع بنجاح';
    }

    private function articleRecord(): Article
    {
        $record = $this->getRecord();

        if (! $record instanceof Article) {
            throw new \LogicException('The article editor received an invalid record.');
        }

        return $record;
    }
}
