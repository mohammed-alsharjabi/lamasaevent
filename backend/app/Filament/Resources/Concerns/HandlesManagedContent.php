<?php

namespace App\Filament\Resources\Concerns;

use App\Enums\ContentStatus;
use App\Services\ContentPublishingService;

trait HandlesManagedContent
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->authorizePublication($data, null);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $status = $this->getRecord()->getAttribute('status');
        $oldStatus = $status instanceof ContentStatus ? $status->value : (string) $status;
        $this->authorizePublication($data, $oldStatus);
        $data['updated_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        app(ContentPublishingService::class)->sync($this->record, auth()->id());
    }

    protected function afterSave(): void
    {
        app(ContentPublishingService::class)->sync($this->record, auth()->id());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function authorizePublication(array $data, ?string $oldStatus): void
    {
        $newStatus = $data['status'] instanceof ContentStatus
            ? $data['status']->value
            : ($data['status'] ?? ContentStatus::Draft->value);

        if ($newStatus !== ContentStatus::Published->value || $oldStatus === $newStatus) {
            return;
        }

        abort_unless(
            auth()->user()?->hasPermission($this->permissionGroup().'.publish'),
            403,
        );
    }

    private function permissionGroup(): string
    {
        $model = class_basename(static::getResource()::getModel());

        return match ($model) {
            'Article' => 'articles',
            'Service' => 'services',
            'Area' => 'areas',
            'Page' => 'pages',
            'ServiceCategory' => 'service-categories',
            default => 'galleries',
        };
    }
}
