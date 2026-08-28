<?php

namespace App\Filament\Resources\SiteSettings\Pages;

use App\Filament\Resources\SiteSettings\SiteSettingResource;
use App\Filament\Support\SiteSettingPresentation;
use App\Models\SiteSetting;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditSiteSetting extends EditRecord
{
    protected static string $resource = SiteSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'تعديل: '.SiteSettingPresentation::title($this->settingKey());
    }

    public function getSubheading(): string|Htmlable|null
    {
        return SiteSettingPresentation::location($this->settingKey());
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('تم حفظ إعدادات الموقع')
            ->body('بدأ تحديث الموقع تلقائيًا. سيظهر التغيير عادة خلال أقل من دقيقة.');
    }

    private function settingKey(): ?string
    {
        $record = $this->getRecord();

        return $record instanceof SiteSetting ? $record->key : null;
    }
}
