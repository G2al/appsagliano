<?php

namespace App\Filament\Resources\MovementResource\Pages;

use App\Filament\Resources\MovementResource;
use App\Models\Movement;
use App\Models\Station;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EditMovement extends EditRecord
{
    protected static string $resource = MovementResource::class;

    protected ?int $previousStationId = null;
    protected float $previousStationCharge = 0.0;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->using(function (Movement $record): bool {
                    $stationId = $record->station_id;
                    $charge = (float) ($record->station_charge ?? 0);

                    if ($stationId && $charge > 0) {
                        Station::adjustCreditBalance((int) $stationId, $charge);
                    }

                    return (bool) $record->delete();
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->previousStationId = $this->record->station_id;
        $this->previousStationCharge = (float) ($this->record->station_charge ?? 0);

        // Traccia chi modifica dal backoffice (admin).
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
            $newStationId = (int) ($data['station_id'] ?? 0);
            $oldStationId = (int) ($this->previousStationId ?? 0);

            $available = ($newStationId === $oldStationId)
                ? ($balance + $this->previousStationCharge)
                : $balance;

            if ($available < (float) $data['station_charge']) {
                throw ValidationException::withMessages([
                    'price' => ['Credito insufficiente sulla stazione selezionata.'],
                ]);
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $oldStationId = $this->previousStationId;
        $oldCharge = $this->previousStationCharge;
        $newStationId = $this->record->station_id;
        $newCharge = (float) ($this->record->station_charge ?? 0);

        $deltas = [];

        if ($oldStationId && $oldCharge > 0) {
            $deltas[(int) $oldStationId] = ($deltas[(int) $oldStationId] ?? 0.0) + $oldCharge;
        }

        if ($newStationId && $newCharge > 0) {
            $deltas[(int) $newStationId] = ($deltas[(int) $newStationId] ?? 0.0) - $newCharge;
        }

        foreach ($deltas as $stationId => $delta) {
            if ((float) $delta === 0.0) {
                continue;
            }

            Station::adjustCreditBalance((int) $stationId, (float) $delta);
        }
    }
}
