<?php

namespace App\Filament\Resources\VehicleResource\Pages;

use App\Filament\Resources\VehicleResource;
use App\Models\Vehicle;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListVehicles extends ListRecords
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('download_revenue_attachments')
                ->label('Scarica entrate')
                ->icon('heroicon-o-arrow-down-tray')
                ->modalHeading('Scarica allegati entrate veicoli')
                ->form(VehicleResource::getRevenueDownloadFormSchema())
                ->action(function (array $data) {
                    $vehicleIds = Vehicle::query()
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->all();

                    if (empty($vehicleIds)) {
                        Notification::make()
                            ->title('Nessun veicolo disponibile')
                            ->warning()
                            ->send();

                        return;
                    }

                    return redirect()->route('vehicles.revenues.download', [
                        'token' => VehicleResource::buildRevenueDownloadToken($vehicleIds, $data['month'] ?? null),
                    ]);
                }),
        ];
    }
}
