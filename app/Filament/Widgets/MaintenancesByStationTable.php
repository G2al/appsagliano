<?php

namespace App\Filament\Widgets;

use App\Models\Maintenance;
use App\Models\Station;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

class MaintenancesByStationTable extends BaseWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Manutenzioni per stazione';

    protected function getTableQuery(): Builder
    {
        [$start, $end] = $this->getDateRange();

        return Maintenance::query()
            ->selectRaw("
                MIN(maintenances.id) as id,
                stations.id as station_id,
                stations.name,
                SUM(maintenances.price) as price_total,
                COUNT(*) as maintenances_count,
                COUNT(DISTINCT maintenances.vehicle_id) as vehicles_count
            ")
            ->join('stations', 'stations.id', '=', 'maintenances.station_id')
            ->whereBetween('maintenances.date', [$start, $end])
            ->groupBy('stations.id', 'stations.name')
            ->orderByDesc('price_total');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')
                ->label('Stazione')
                ->searchable(),
            Tables\Columns\TextColumn::make('price_total')
                ->label('Spesa')
                ->money('EUR', true)
                ->sortable(),
            Tables\Columns\TextColumn::make('maintenances_count')
                ->label('Interventi')
                ->sortable(),
            Tables\Columns\TextColumn::make('vehicles_count')
                ->label('Veicoli')
                ->sortable(),
            Panel::make([
                Tables\Columns\TextColumn::make('vehicles_detail')
                    ->label('Veicoli in questa stazione')
                    ->state(fn ($record) => $this->vehiclesForStation($record->station_id))
                    ->listWithLineBreaks()
                    ->limitList(10),
                Tables\Columns\TextColumn::make('movements_detail')
                    ->label('Movimenti')
                    ->state(fn ($record) => $this->movementsForStation($record->station_id))
                    ->listWithLineBreaks()
                    ->limitList(15),
            ])->collapsible(),
        ];
    }

    private function vehiclesForStation(int $stationId): array
    {
        [$start, $end] = $this->getDateRange();

        return Maintenance::query()
            ->selectRaw("
                vehicles.id,
                vehicles.plate,
                vehicles.name,
                COUNT(*) as movements_count,
                SUM(price) as price_total
            ")
            ->join('vehicles', 'vehicles.id', '=', 'maintenances.vehicle_id')
            ->where('maintenances.station_id', $stationId)
            ->whereBetween('maintenances.date', [$start, $end])
            ->groupBy('vehicles.id', 'vehicles.plate', 'vehicles.name')
            ->orderByDesc('price_total')
            ->get()
            ->map(function ($row) {
                $label = trim(($row->plate ? $row->plate . ' - ' : '') . ($row->name ?? 'Veicolo'));
                $price = number_format((float) $row->price_total, 2, ',', '.');
                return "{$label}: € {$price} ({$row->movements_count} mov)";
            })
            ->all();
    }

    private function movementsForStation(int $stationId): array
    {
        [$start, $end] = $this->getDateRange();

        return Maintenance::with(['vehicle'])
            ->where('station_id', $stationId)
            ->whereBetween('date', [$start, $end])
            ->orderByDesc('date')
            ->limit(50)
            ->get()
            ->map(function (Maintenance $m) {
                $date = $m->date?->format('d/m/Y H:i') ?? 'N/D';
                $vehicle = trim(($m->vehicle?->plate ? $m->vehicle->plate . ' - ' : '') . ($m->vehicle?->name ?? 'Veicolo'));
                $price = $m->price !== null ? '€ ' . number_format((float) $m->price, 2, ',', '.') : '€ 0,00';
                return "{$date} | {$vehicle} | {$price}";
            })
            ->all();
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

