<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Concerns\HandlesManagedContent;
use App\Filament\Resources\Services\ServiceResource;
use App\Models\Service;
use App\Services\ServiceFormDataMapper;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateService extends CreateRecord
{
    use HandlesManagedContent {
        mutateFormDataBeforeCreate as managedMutateFormDataBeforeCreate;
    }

    protected static string $resource = ServiceResource::class;

    private bool $redirectToPreview = false;

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = app(ServiceFormDataMapper::class)->forStorage($data);
        $data = $this->managedMutateFormDataBeforeCreate($data);
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
            Action::make('previewDraft')
                ->label('معاينة')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->action('previewDraft'),
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
        $this->data['status'] = 'draft';
        $this->create();
    }

    public function previewDraft(): void
    {
        $this->data['status'] = 'draft';
        $this->redirectToPreview = true;
        $this->create();
    }

    public function publish(): void
    {
        $this->data['status'] = 'published';
        $this->create();
    }

    public function publishAndCreateAnother(): void
    {
        $this->data['status'] = 'published';
        $this->create(another: true);
    }

    protected function getRedirectUrl(): string
    {
        if ($this->redirectToPreview && $this->record instanceof Service) {
            return route('admin.services.preview', $this->record);
        }

        return parent::getRedirectUrl();
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'تم حفظ الخدمة وإرسال التحديث للواجهة بنجاح';
    }
}
