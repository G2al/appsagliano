<?php

namespace App\Filament\Widgets;

use App\Models\Movement;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class RefuelsByVehicleStationTable extends BaseWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Spesa per veicolo / stazione';

    protected function getTableQuery(): Builder
    {
        [$start, $end] = $this->resolveDateRange();

        return Movement::query()
            ->selectRaw("
                MIN(movements.id) as id,
                vehicles.id as vehicle_id,
                vehicles.plate,
                vehicles.name as vehicle_name,
                stations.id as station_id,
                stations.name as station_name,
                SUM(movements.price) as price_total,
                SUM(movements.liters) as liters_total,
                COUNT(*) as movements_count
            ")
            ->join('vehicles', 'vehicles.id', '=', 'movements.vehicle_id')
            ->join('stations', 'stations.id', '=', 'movements.station_id')
            ->whereBetween('movements.date', [$start, $end])
            ->groupBy('vehicles.id', 'vehicles.plate', 'vehicles.name', 'stations.id', 'stations.name');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('plate')
                ->label('Veicolo')
                ->formatStateUsing(fn ($state, $record) => trim(($record->plate ? $record->plate . ' - ' : '') . ($record->vehicle_name ?? '')))
                ->searchable(['plate', 'vehicle_name']),
            Tables\Columns\TextColumn::make('station_name')
                ->label('Stazione')
                ->searchable(),
            Tables\Columns\TextColumn::make('liters_total')
                ->label('Litri')
                ->numeric(2, locale: 'it')
                ->sortable(),
            Tables\Columns\TextColumn::make('price_total')
                ->label('Spesa')
                ->money('EUR', true)
                ->sortable(),
            Tables\Columns\TextColumn::make('movements_count')
                ->label('Movimenti')
                ->sortable(),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('dettagli')
                ->label('Vedi movimenti')
                ->icon('heroicon-o-list-bullet')
                ->modalHeading('Movimenti veicolo / stazione')
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Chiudi')
                ->modalContent(fn ($record) => new HtmlString(
                    $this->renderDetails(
                        (int) $record->vehicle_id,
                        (int) $record->station_id,
                        trim(($record->plate ? $record->plate . ' - ' : '') . ($record->vehicle_name ?? '')),
                        $record->station_name ?? 'Stazione'
                    )
                )),
        ];
    }

    private function renderDetails(int $vehicleId, int $stationId, string $vehicleLabel, string $stationLabel): string
    {
        [$start, $end] = $this->resolveDateRange();

        $rows = Movement::with(['vehicle', 'station'])
            ->where('vehicle_id', $vehicleId)
            ->where('station_id', $stationId)
            ->whereBetween('date', [$start, $end])
            ->orderByDesc('date')
            ->limit(50)
            ->get()
            ->map(function (Movement $m) {
                $date = $m->date?->format('d/m/Y H:i') ?? 'N/D';
                $liters = $m->liters !== null ? number_format((float) $m->liters, 2, ',', '.') . ' L' : '0,00 L';
                $price = $m->price !== null ? '€ ' . number_format((float) $m->price, 2, ',', '.') : '€ 0,00';
                $note = $m->notes ?: '';
                $attachment = $m->photo_path
                    ? '<a href="' . e(asset('storage/' . $m->photo_path)) . '" target="_blank" class="text-primary-500">Stampa allegato</a>'
                    : 'N/D';

                return "<tr>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$date}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$liters}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$price}</td>
                    <td class=\"px-2 py-1 text-sm\">" . e($note) . "</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$attachment}</td>
                </tr>";
            })->implode('');

        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="px-2 py-3 text-sm text-center text-gray-500">Nessun movimento</td></tr>';
        }

        return <<<HTML
            <div class="space-y-2">
                <div class="text-sm font-semibold">Veicolo: {$vehicleLabel}</div>
                <div class="text-sm font-semibold">Stazione: {$stationLabel}</div>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-2 py-2 font-medium">Data</th>
                                <th class="px-2 py-2 font-medium">Litri</th>
                                <th class="px-2 py-2 font-medium">Prezzo</th>
                                <th class="px-2 py-2 font-medium">Note</th>
                                <th class="px-2 py-2 font-medium">Allegato</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$rows}
                        </tbody>
                    </table>
                </div>
            </div>
        HTML;
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
