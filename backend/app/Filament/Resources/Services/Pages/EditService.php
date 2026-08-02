<?php

namespace App\Filament\Resources\Services\Pages;

use App\Enums\ContentStatus;
use App\Filament\Resources\Concerns\HandlesManagedContent;
use App\Filament\Resources\Services\ServiceResource;
use App\Models\SeoMeta;
use App\Models\Service;
use App\Models\User;
use App\Services\ServiceDuplicationService;
use App\Services\ServiceFormDataMapper;
use App\Services\SlugRedirectService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EditService extends EditRecord
{
    use HandlesManagedContent {
        mutateFormDataBeforeSave as managedMutateFormDataBeforeSave;
    }

    protected static string $resource = ServiceResource::class;

    public string $autosaveMessage = 'الحفظ التلقائي للمسودة مفعّل';

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Service $record */
        $record = $this->serviceRecord();
        $data['developer_mode'] = false;

        // These attributes are intentionally hidden from API serialization,
        // so hydrate them explicitly for the authorized Filament editor.
        foreach ([
            'seo_overrides', 'cta_overrides', 'uses_generated_defaults',
            'seo_title_override', 'meta_description_override', 'slug_override',
            'canonical_override', 'robots_override', 'og_title_override',
            'og_description_override', 'og_image_override', 'schema_override',
            'target_search_phrase', 'hero_alt_override',
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
            $data['cta_overrides'] = [
                'label' => $record->cta_label,
                'url' => $record->cta_url,
                'mode' => $record->whatsapp_enabled ? 'show' : 'hide',
            ];
        }

        return app(ServiceFormDataMapper::class)->forForm($data, $record);
    }

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = app(ServiceFormDataMapper::class)->forStorage($data);
        $data = $this->managedMutateFormDataBeforeSave($data);
        $data['uses_generated_defaults'] = true;

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
                ->visible(fn (): bool => auth()->user()?->hasPermission('services.publish') ?? false),
            Action::make('publishAndCreateAnother')
                ->label('نشر وإضافة خدمة جديدة')
                ->icon('heroicon-o-plus-circle')
                ->action('publishAndCreateAnother')
                ->visible(fn (): bool => auth()->user()?->hasPermission('services.publish') ?? false),
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
        $this->redirect(route('admin.services.preview', $this->serviceRecord()));
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
        $this->redirect(ServiceResource::getUrl('create'));
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $requestedSlug = Str::slug((string) ($data['slug_override'] ?? ''));
        $wasPublished = $record->getRawOriginal('status') === ContentStatus::Published->value;
        $record = parent::handleRecordUpdate($record, $data);

        if (
            $record instanceof Service
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
                ->url(fn (): string => route('admin.services.preview', $this->serviceRecord()))
                ->openUrlInNewTab(),
            Action::make('duplicateService')
                ->label('تكرار الخدمة')
                ->icon('heroicon-o-square-2-stack')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('إنشاء نسخة مسودة من الخدمة؟')
                ->modalDescription('سيتم إنشاء رابط جديد تلقائيًا، ولن تتأثر الخدمة الأصلية.')
                ->action(function (): mixed {
                    $duplicate = app(ServiceDuplicationService::class)
                        ->duplicate($this->serviceRecord(), auth()->id());

                    return redirect(ServiceResource::getUrl('edit', ['record' => $duplicate]));
                }),
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    public function autosaveDraft(): void
    {
        /** @var Service $record */
        $record = $this->serviceRecord();

        if ($record->status !== ContentStatus::Draft) {
            $this->autosaveMessage = 'التعديلات على خدمة منشورة تُحفظ يدويًا لحمايتها';

            return;
        }

        $currentHash = md5((string) str(
            json_encode($this->data, JSON_UNESCAPED_UNICODE),
        )->replace('\\', ''));

        if (isset($this->savedDataHash) && hash_equals($this->savedDataHash, $currentHash)) {
            return;
        }

        if (blank($this->data['title'] ?? null)) {
            $this->autosaveMessage = 'اكتب اسم الخدمة ليبدأ الحفظ التلقائي';

            return;
        }

        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
        $this->autosaveMessage = 'حُفظت المسودة تلقائيًا عند '.now()->format('H:i');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'تم حفظ الخدمة وإرسال التحديث للواجهة بنجاح';
    }

    private function serviceRecord(): Service
    {
        $record = $this->getRecord();

        if (! $record instanceof Service) {
            throw new \LogicException('The service editor received an invalid record.');
        }

        return $record;
    }
}
