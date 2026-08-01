<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Concerns\HandlesManagedContent;
use App\Filament\Resources\Services\ServiceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateService extends CreateRecord
{
    use HandlesManagedContent {
        mutateFormDataBeforeCreate as managedMutateFormDataBeforeCreate;
    }

    protected static string $resource = ServiceResource::class;

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->managedMutateFormDataBeforeCreate($data);
        $data['uses_generated_defaults'] = true;

        return $data;
    }

    /** @return array<Action> */
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()->label('حفظ الخدمة'),
            $this->getCreateAnotherFormAction()
                ->label('حفظ وإنشاء خدمة أخرى')
                ->color('gray'),
            $this->getCancelFormAction()->label('إلغاء'),
        ];
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'تم حفظ الخدمة وإرسال التحديث للواجهة بنجاح';
    }
}
