<?php

namespace App\Filament\Resources\Services\Pages;

use App\Enums\ContentStatus;
use App\Filament\Resources\Concerns\HandlesManagedContent;
use App\Filament\Resources\Services\ServiceResource;
use App\Models\SeoMeta;
use App\Models\Service;
use App\Services\ServiceDuplicationService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

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

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->managedMutateFormDataBeforeSave($data);
        $data['uses_generated_defaults'] = true;

        return $data;
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
