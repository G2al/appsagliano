<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithReportTableChecks;
use App\Filament\Widgets\Concerns\RendersTripDetails;
use App\Models\Trip;
use Filament\Tables;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class TripsByVehicleTable extends BaseWidget
{
    use InteractsWithPageFilters;
    use InteractsWithReportTableChecks;
    use RendersTripDetails;

    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Viaggi per veicolo';

    protected function getTableQuery(): Builder
    {
        [$start, $end] = $this->getReportTableCheckDateRange();

        return Trip::query()
            ->selectRaw("
                MIN(trips.id) as id,
                vehicles.id as vehicle_id,
                vehicles.plate,
                vehicles.name,
                COUNT(*) as trips_count,
                SUM(CASE WHEN trips.price IS NOT NULL THEN 1 ELSE 0 END) as certified_count,
                COALESCE(SUM(trips.price), 0) as price_total
            ")
            ->join('vehicles', 'vehicles.id', '=', 'trips.vehicle_id')
            ->whereBetween('trips.date', [$start, $end])
            ->groupBy('vehicles.id', 'vehicles.plate', 'vehicles.name');
    }

    protected function getTableColumns(): array
    {
        return [
            $this->getReportTableCheckColumn(),
            Tables\Columns\TextColumn::make('plate')
                ->label('Veicolo')
                ->formatStateUsing(fn ($state, $record) => trim(($record->plate ? $record->plate . ' - ' : '') . ($record->name ?? '')))
                ->searchable(['vehicles.plate', 'vehicles.name']),
            Tables\Columns\TextColumn::make('trips_count')
                ->label('Viaggi')
                ->sortable(),
            Tables\Columns\TextColumn::make('certified_count')
                ->label('Certificati')
                ->sortable(),
            Tables\Columns\TextColumn::make('pending_count')
                ->label('Da certificare')
                ->state(fn ($record) => (int) $record->trips_count - (int) $record->certified_count),
            Tables\Columns\TextColumn::make('price_total')
                ->label('Totale certificato')
                ->money('EUR', true)
                ->sortable(),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            $this->getReportTableCheckedFilter(),
        ];
    }

    protected function getReportTableRowKeySql(): string
    {
        return "CONCAT('vehicle:', vehicles.id)";
    }

    protected function getReportTableRowKey(Model $record): string
    {
        return 'vehicle:' . (int) $record->vehicle_id;
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('dettagli')
                ->label('Vedi viaggi')
                ->icon('heroicon-o-list-bullet')
                ->modalHeading('Viaggi del veicolo')
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Chiudi')
                ->modalContent(function ($record) {
                    [$start, $end] = $this->getReportTableCheckDateRange();

                    $query = Trip::query()
                        ->where('vehicle_id', (int) $record->vehicle_id)
                        ->whereBetween('date', [$start, $end]);

                    return new HtmlString($this->renderTripDetails(
                        $query,
                        'Veicolo: ' . trim(($record->plate ? $record->plate . ' - ' : '') . ($record->name ?? ''))
                    ));
                }),
        ];
    }
}
