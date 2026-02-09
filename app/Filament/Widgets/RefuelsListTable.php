<?php

namespace App\Filament\Widgets;

use App\Models\Movement;
use App\Models\Station;
use App\Models\Vehicle;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class RefuelsListTable extends BaseWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Movimenti rifornimento';

    protected function getTableQuery(): Builder
    {
        [$start, $end] = $this->resolveDateRange();

        return Movement::query()
            ->with(['vehicle', 'station'])
            ->whereBetween('date', [$start, $end])
            ->orderByDesc('date');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('date')
                ->label('Data')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
            Tables\Columns\TextColumn::make('vehicle.plate')
                ->label('Veicolo')
                ->formatStateUsing(fn ($state, $record) => trim(($state ? $state . ' - ' : '') . ($record->vehicle?->name ?? 'Veicolo')))
                ->searchable(['vehicles.plate', 'vehicles.name'])
                ->sortable(),
            Tables\Columns\TextColumn::make('station.name')
                ->label('Stazione')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('liters')
                ->label('Litri')
                ->numeric(2)
                ->sortable(),
            Tables\Columns\TextColumn::make('km_per_liter')
                ->label('Km/L')
                ->formatStateUsing(fn ($state) => $state === null ? 'N/D' : number_format((float) $state, 2, ',', '.'))
                ->sortable(),
            Tables\Columns\TextColumn::make('price')
                ->label('Prezzo')
                ->money('EUR', true)
                ->sortable(),
            Tables\Columns\TextColumn::make('notes')
                ->label('Note')
                ->limit(40)
                ->toggleable(),
            Tables\Columns\TextColumn::make('photo_path')
                ->label('Allegato')
                ->state(fn ($record) => $record->photo_path ? 'Stampa' : 'N/D')
                ->url(fn ($record) => $record->photo_path ? asset('storage/' . $record->photo_path) : null, true)
                ->openUrlInNewTab()
                ->toggleable(),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            Tables\Filters\SelectFilter::make('vehicle_id')
                ->label('Veicolo')
                ->options(fn () => Vehicle::query()
                    ->orderBy('plate')
                    ->get()
                    ->mapWithKeys(fn ($v) => [$v->id => trim(($v->plate ? $v->plate . ' - ' : '') . ($v->name ?? ''))])
                    ->toArray()),
            Tables\Filters\SelectFilter::make('station_id')
                ->label('Stazione')
                ->options(fn () => Station::query()->orderBy('name')->pluck('name', 'id')->toArray()),
        ];
    }

    private function resolveDateRange(): array
    {
        $filters = $this->filters ?? [];
        $startRaw = $filters['start_date'] ?? null;
        $endRaw = $filters['end_date'] ?? null;

        $start = Carbon::parse($startRaw ?: now()->startOfMonth())->startOfDay();
        $end = Carbon::parse($endRaw ?: now())->endOfDay();

        return [$start, $end];
    }
}
