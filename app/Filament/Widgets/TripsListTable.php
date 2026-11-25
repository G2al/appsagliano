<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithReportTableChecks;
use App\Models\Platform;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\TripExcelExporter;
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
    protected static ?string $heading = 'Elenco viaggi';

    protected function getTableQuery(): Builder
    {
        [$start, $end] = $this->getReportTableCheckDateRange();

        return Trip::query()
            ->with(['user', 'vehicle', 'platform', 'attachments'])
            ->join('users', 'users.id', '=', 'trips.user_id')
            ->whereBetween('trips.date', [$start, $end])
            ->orderByDesc('trips.date')
            ->select('trips.*');
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
                ->formatStateUsing(fn ($state, Trip $record) => $record->user?->full_name ?? 'N/D')
                ->searchable(['users.name', 'users.surname']),
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
            Tables\Columns\TextColumn::make('distance_km')
                ->label('Km percorsi')
                ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 0, ',', '.') . ' km' : '—')
                ->sortable(),
            Tables\Columns\TextColumn::make('price')
                ->label('Prezzo')
                ->money('EUR', true)
                ->placeholder('—')
                ->sortable(),
            Tables\Columns\TextColumn::make('attachments_count')
                ->label('Allegati')
                ->state(fn (Trip $record) => $record->attachments->isNotEmpty() ? 'Vedi (' . $record->attachments->count() . ')' : 'N/D')
                ->url(fn (Trip $record) => $record->attachments->isNotEmpty() ? $record->attachments->first()->url : null, true)
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

    protected function getTableHeaderActions(): array
    {
        return [
            Tables\Actions\Action::make('export_excel')
                ->label('Esporta Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    [$start, $end] = $this->getReportTableCheckDateRange();

                    $trips = Trip::query()
                        ->with(['user', 'vehicle', 'platform', 'attachments'])
                        ->whereBetween('date', [$start, $end])
                        ->orderBy('date')
                        ->get();

                    $spreadsheet = TripExcelExporter::build('Elenco viaggi', $trips);

                    return TripExcelExporter::stream($spreadsheet, 'elenco-viaggi.xlsx');
                }),
        ];
    }
}
