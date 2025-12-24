<?php

namespace App\Filament\Resources\MovementResource\Pages;

use App\Filament\Resources\MovementResource;
use App\Models\Station;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateMovement extends CreateRecord
{
    protected static string $resource = MovementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Se l'admin crea un movimento da Filament, salviamo updated_by.
        if (Auth::check()) {
            $data['updated_by'] = Auth::id();
        }

        $station = isset($data['station_id'])
            ? Station::select('id', 'credit_balance')->find($data['station_id'])
            : null;

        $data['station_charge'] = ($station && $station->credit_balance !== null)
            ? (float) ($data['price'] ?? 0)
            : 0.0;

        if ($data['station_charge'] > 0) {
            $balance = (float) $station->credit_balance;
            if ($balance < $data['station_charge']) {
                throw ValidationException::withMessages([
                    'price' => ['Credito insufficiente sulla stazione selezionata.'],
                ]);
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $stationId = $this->record?->station_id;
        $charge = (float) ($this->record?->station_charge ?? 0);

        if (! $stationId || $charge <= 0) {
            return;
        }

        Station::whereKey($stationId)->decrement('credit_balance', $charge);
    }
}
