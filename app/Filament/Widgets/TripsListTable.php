<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithReportTableChecks;
use App\Models\Filiale;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Tables;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TripsListTable extends BaseWidget
{
    use InteractsWithPageFilters;
    use InteractsWithReportTableChecks;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Elenco bordero';

    protected function getTableQuery(): Builder
    {
        [$start, $end] = $this->getReportTableCheckDateRange();

        return Trip::query()
            ->with(['user', 'vehicle', 'filiale.goodsType', 'attachments'])
            ->whereBetween('date', [$start, $end])
            ->orderByDesc('date');
    }

    protected function getTableColumns(): array
    {
        return [
            $this->getReportTableCheckColumn(),
            Tables\Columns\TextColumn::make('date')
                ->label('Data')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
            Tables\Columns\TextColumn::make('user.name')
                ->label('Autista')
                ->formatStateUsing(fn ($state, Trip $record) => $record->user?->full_name ?? 'N/D'),
            Tables\Columns\TextColumn::make('vehicle.plate')
                ->label('Veicolo')
                ->formatStateUsing(fn ($state, Trip $record) => trim(($record->vehicle?->plate ? $record->vehicle->plate . ' - ' : '') . ($record->vehicle?->name ?? ''))),
            Tables\Columns\TextColumn::make('filiale.name')
                ->label('Filiale')
                ->formatStateUsing(fn ($state, Trip $record) => $record->filiale ? "{$record->filiale->codice} - {$record->filiale->name}" : 'N/D'),
            Tables\Columns\TextColumn::make('filiale.goodsType.name')
                ->label('Tipologia')
                ->placeholder('N/D')
                ->badge(),
            Tables\Columns\TextColumn::make('delivery_note_number')
                ->label('Numero bordero')
                ->searchable(),
            Tables\Columns\TextColumn::make('scarichi')
                ->label('Scarichi'),
            Tables\Columns\TextColumn::make('price')
                ->label('Prezzo')
                ->money('EUR', true)
                ->placeholder('Da certificare')
                ->sortable(),
            Tables\Columns\TextColumn::make('attachments_count')
                ->label('Allegati')
                ->state(fn (Trip $record) => $record->attachments->isNotEmpty() ? 'Apri (' . $record->attachments->count() . ')' : 'N/D')
                ->url(fn (Trip $record) => $record->attachments->isNotEmpty() ? route('trips.attachment', $record) : null, true)
                ->openUrlInNewTab(),
        ];
    }

    protected function getReportTableRowKeySql(): string
    {
        return "CONCAT('trip:', trips.id)";
    }

    protected function getReportTableRowKey(Model $record): string
    {
        return 'trip:' . $record->getKey();
    }

    protected function getTableFilters(): array
    {
        return [
            $this->getReportTableCheckedFilter(),
            Tables\Filters\SelectFilter::make('vehicle_id')
                ->label('Veicolo')
                ->options(fn () => Vehicle::query()
                    ->orderBy('plate')
                    ->get()
                    ->mapWithKeys(fn ($v) => [$v->id => trim(($v->plate ? $v->plate . ' - ' : '') . ($v->name ?? ''))])
                    ->toArray()),
            Tables\Filters\SelectFilter::make('user_id')
                ->label('Autista')
                ->options(fn () => User::query()
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (User $u) => [$u->id => $u->full_name])
                    ->toArray()),
            Tables\Filters\SelectFilter::make('filiale_id')
                ->label('Filiale')
                ->options(fn () => Filiale::query()->orderBy('name')->pluck('name', 'id')->toArray()),
        ];
    }
}
