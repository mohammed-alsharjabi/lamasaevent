<?php

namespace App\Filament\Resources\Concerns;

use App\Services\SlugRedirectService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;

trait HasSlugRedirectAction
{
    protected function slugRedirectAction(): Action
    {
        return Action::make('changeSlug')
            ->label('تغيير الرابط مع تحويل 301')
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->color('warning')
            ->visible(fn (): bool => (bool) $this->record->isPublished())
            ->authorize(
                fn (): bool => auth()->user()?->hasPermission(
                    $this->slugPermissionGroup().'.update',
                ) ?? false,
            )
            ->schema([
                TextInput::make('new_slug')
                    ->label('الرابط المختصر الجديد')
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->required(),
            ])
            ->requiresConfirmation()
            ->action(function (array $data): void {
                app(SlugRedirectService::class)->change(
                    $this->record,
                    $data['new_slug'],
                    auth()->user(),
                );

                $this->refreshFormData(['slug']);
            });
    }

    private function slugPermissionGroup(): string
    {
        return match (class_basename($this->record)) {
            'Article' => 'articles',
            'Service' => 'services',
            'ServiceCategory' => 'service-categories',
            default => 'areas',
        };
    }
}
