<?php

namespace App\Filament\Resources\PlatformResource\Pages;

use App\Filament\Resources\PlatformResource;
use App\Models\Platform;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPlatform extends EditRecord
{
    protected static string $resource = PlatformResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action): void {
                    /** @var Platform $platform */
                    $platform = $this->getRecord();

                    if (! $platform->trips()->withTrashed()->exists()) {
                        return;
                    }

                    Notification::make()
                        ->danger()
                        ->title('Impossibile eliminare')
                        ->body('Questa piattaforma e usata da uno o piu viaggi.')
                        ->send();

                    $action->cancel();
                }),
        ];
    }
}
