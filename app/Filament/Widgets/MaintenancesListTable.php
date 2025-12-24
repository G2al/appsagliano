<?php

namespace App\Filament\Widgets;

use App\Models\Maintenance;
use App\Models\Supplier;
use App\Models\Vehicle;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class MaintenancesListTable extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Movimenti manutenzione';

    protected function getTableQuery(): Builder
    {
        [$start, $end] = $this->getDateRange();

        return Maintenance::query()
            ->with(['vehicle', 'supplier'])
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
            Tables\Columns\TextColumn::make('supplier.name')
                ->label('Fornitore')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('km_current')
                ->label('Km manutenzione')
                ->numeric(0)
                ->sortable(),
            Tables\Columns\TextColumn::make('price')
                ->label('Prezzo')
                ->money('EUR', true)
                ->sortable(),
            Tables\Columns\TextColumn::make('invoice_number')
                ->label('N° bolla')
                ->toggleable()
                ->searchable(),
            Tables\Columns\TextColumn::make('notes')
                ->label('Note')
                ->limit(40)
                ->toggleable(),
            Tables\Columns\TextColumn::make('attachment_url')
                ->label('Allegato')
                ->state(fn ($record) => $record->attachment_url ? 'Stampa' : 'N/D')
                ->url(fn ($record) => $record->attachment_url ? route('maintenances.attachment', $record) : null, true)
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
            Tables\Filters\SelectFilter::make('supplier_id')
                ->label('Fornitore')
                ->options(fn () => Supplier::query()->orderBy('name')->pluck('name', 'id')->toArray()),
        ];
    }

    private function getDateRange(): array
    {
        $filters = $this->tableFilters ?? [];
        $dateFilter = $filters['date_range'] ?? [];
        if (is_array($dateFilter) && array_key_exists('value', $dateFilter)) {
            $dateFilter = $dateFilter['value'] ?? [];
        }

        $start = Carbon::parse($dateFilter['start'] ?? now()->startOfMonth())->startOfDay();
        $end = Carbon::parse($dateFilter['end'] ?? now())->endOfDay();

        return [$start, $end];
    }
}
