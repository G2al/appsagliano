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
            ? Station::select('id', 'credit_balance', 'uses_vouchers')->find($data['station_id'])
            : null;

        $requestedVoucher = (bool) ($data['is_voucher'] ?? false);
        $canUseVoucher = (bool) ($station?->uses_vouchers ?? false);

        if ($requestedVoucher && ! $canUseVoucher) {
            throw ValidationException::withMessages([
                'is_voucher' => ['La stazione selezionata non consente rifornimenti con buono.'],
            ]);
        }

        $data['is_voucher'] = $canUseVoucher && $requestedVoucher;

        $data['station_charge'] = ($station && $station->credit_balance !== null)
            ? ($data['is_voucher'] ? 0.0 : (float) ($data['price'] ?? 0))
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

        Station::adjustCreditBalance((int) $stationId, -$charge);
    }
}
