<?php

namespace App\Filament\Resources\FilialeResource\Pages;

use App\Filament\Resources\FilialeResource;
use App\Models\Filiale;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditFiliale extends EditRecord
{
    protected static string $resource = FilialeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action): void {
                    /** @var Filiale $filiale */
                    $filiale = $this->getRecord();

                    if (! $filiale->trips()->withTrashed()->exists()) {
                        return;
                    }

                    Notification::make()
                        ->danger()
                        ->title('Impossibile eliminare')
                        ->body('Questa filiale e usata da uno o piu record bordero.')
                        ->send();

                    $action->cancel();
                }),
        ];
    }
}
