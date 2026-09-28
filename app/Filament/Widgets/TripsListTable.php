<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithReportTableChecks;
use App\Models\Platform;
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

    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Elenco viaggi';

    protected function getTableQuery(): Builder
    {
        [$start, $end] = $this->getReportTableCheckDateRange();

        return Trip::query()
            ->with(['user', 'vehicle', 'platform'])
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
            Tables\Columns\TextColumn::make('platform.name')
                ->label('Piattaforma'),
            Tables\Columns\TextColumn::make('destinations')
                ->label('Destinazioni')
                ->state(fn (Trip $record) => $record->destinations_label)
                ->wrap()
                ->limit(60),
            Tables\Columns\TextColumn::make('goods_type')
                ->label('Dicitura')
                ->formatStateUsing(fn ($state) => Trip::goodsTypeOptions()[$state] ?? $state)
                ->badge(),
            Tables\Columns\TextColumn::make('delivery_note_number')
                ->label('Bolla')
                ->searchable(),
            Tables\Columns\TextColumn::make('price')
                ->label('Prezzo')
                ->money('EUR', true)
                ->placeholder('Da certificare')
                ->sortable(),
            Tables\Columns\TextColumn::make('attachment_url')
                ->label('Allegato')
                ->state(fn (Trip $record) => $record->attachment_url ? 'Apri' : 'N/D')
                ->url(fn (Trip $record) => $record->attachment_url ? route('trips.attachment', $record) : null, true)
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
            Tables\Filters\SelectFilter::make('platform_id')
                ->label('Piattaforma')
                ->options(fn () => Platform::query()->orderBy('name')->pluck('name', 'id')->toArray()),
        ];
    }
}
