<?php

namespace App\Filament\Resources\VehicleDocumentExpiryResource\Pages;

use App\Filament\Resources\VehicleDocumentExpiryResource;
use App\Models\VehicleDocumentExpiry;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ManageVehicleDocumentExpiries extends ManageRecords
{
    protected static string $resource = VehicleDocumentExpiryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        $today = Carbon::today()->toDateString();

        return [
            'scaduti' => Tab::make('Scaduti')
                ->badge(VehicleDocumentExpiry::query()->where('expiry_date', '<', $today)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('expiry_date', '<', $today)),
            'sette_giorni' => Tab::make('In scadenza (7 giorni)')
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereBetween('expiry_date', [$today, Carbon::today()->addDays(7)->toDateString()])),
            'quindici_giorni' => Tab::make('In scadenza (15 giorni)')
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereBetween('expiry_date', [$today, Carbon::today()->addDays(15)->toDateString()])),
            'trenta_giorni' => Tab::make('In scadenza (30 giorni)')
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereBetween('expiry_date', [$today, Carbon::today()->addDays(30)->toDateString()])),
            'tutti' => Tab::make('Tutti i documenti'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'scaduti';
    }
}
