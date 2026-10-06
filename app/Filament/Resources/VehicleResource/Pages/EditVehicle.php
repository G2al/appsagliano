<?php

namespace App\Filament\Resources\VehicleResource\Pages;

use App\Filament\Resources\VehicleResource;
use App\Models\Vehicle;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action): void {
                    /** @var Vehicle $vehicle */
                    $vehicle = $this->getRecord();

                    if (! $vehicle->trips()->withTrashed()->exists()) {
                        return;
                    }

                    Notification::make()
                        ->danger()
                        ->title('Impossibile eliminare')
                        ->body('Questo veicolo e usato in uno o piu viaggi.')
                        ->send();

                    $action->cancel();
                }),
        ];
    }
}
