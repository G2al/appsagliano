<?php

namespace App\Filament\Widgets;

use App\Models\Maintenance;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

class MaintenancesVehicleSupplierSummaryTable extends BaseWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Totale per veicolo e fornitore';

    protected function getTableQuery(): Builder
    {
        [$start, $end] = $this->getDateRange();

        return Maintenance::query()
            ->selectRaw('
                MIN(maintenances.id) as id,
                vehicles.id as vehicle_id,
                vehicles.plate,
                vehicles.name as vehicle_name,
                suppliers.id as supplier_id,
                suppliers.name as supplier_name,
                SUM(maintenances.price) as price_total,
                COUNT(*) as movements_count
            ')
            ->join('vehicles', 'vehicles.id', '=', 'maintenances.vehicle_id')
            ->join('suppliers', 'suppliers.id', '=', 'maintenances.supplier_id')
            ->whereBetween('maintenances.date', [$start, $end])
            ->groupBy('vehicles.id', 'vehicles.plate', 'vehicles.name', 'suppliers.id', 'suppliers.name');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('vehicle_name')
                ->label('Veicolo')
                ->formatStateUsing(fn ($state, $record) => trim(($record->plate ? $record->plate . ' - ' : '') . ($state ?? 'Veicolo')))
                ->searchable(),
            Tables\Columns\TextColumn::make('supplier_name')
                ->label('Fornitore')
                ->searchable(),
            Tables\Columns\TextColumn::make('price_total')
                ->label('Spesa totale')
                ->money('EUR', true)
                ->sortable(),
            Tables\Columns\TextColumn::make('movements_count')
                ->label('Interventi')
                ->sortable(),
        ];
    }

    private function getDateRange(): array
    {
        $filters = $this->filters ?? [];
        $startRaw = $filters['start_date'] ?? null;
        $endRaw = $filters['end_date'] ?? null;

        $start = Carbon::parse($startRaw ?: now()->startOfMonth())->startOfDay();
        $end = Carbon::parse($endRaw ?: now())->endOfDay();

        return [$start, $end];
    }
}

