<?php

namespace App\Filament\Widgets;

use App\Models\Movement;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class RefuelsByStationTable extends BaseWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Rifornimenti per stazione';

    protected function getTableQuery(): Builder
    {
        [$start, $end] = $this->resolveDateRange();

        return Movement::query()
            ->selectRaw("
                MIN(movements.id) as id,
                stations.id as station_id,
                stations.name,
                SUM(movements.price) as price_total,
                SUM(movements.liters) as liters_total,
                COUNT(*) as movements_count
            ")
            ->join('stations', 'stations.id', '=', 'movements.station_id')
            ->whereBetween('movements.date', [$start, $end])
            ->groupBy('stations.id', 'stations.name');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')
                ->label('Stazione')
                ->searchable(['stations.name']),
            Tables\Columns\TextColumn::make('price_total')
                ->label('Spesa')
                ->money('EUR', true)
                ->sortable(),
            Tables\Columns\TextColumn::make('liters_total')
                ->label('Litri')
                ->numeric(2, locale: 'it')
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
                ->modalHeading('Movimenti della stazione')
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Chiudi')
                ->modalContent(fn ($record) => new HtmlString(
                    $this->renderDetailsForStation((int) $record->station_id, $record->name ?? 'Stazione')
                )),
        ];
    }

    private function renderDetailsForStation(int $stationId, string $label): string
    {
        [$start, $end] = $this->resolveDateRange();

        $rows = Movement::with(['vehicle', 'station'])
            ->where('station_id', $stationId)
            ->whereBetween('date', [$start, $end])
            ->orderByDesc('date')
            ->limit(50)
            ->get()
            ->map(function (Movement $m) {
                $date = $m->date?->format('d/m/Y H:i') ?? 'N/D';
                $vehicle = trim(($m->vehicle?->plate ? $m->vehicle->plate . ' - ' : '') . ($m->vehicle?->name ?? 'Veicolo'));
                $price = $m->price !== null ? '€ ' . number_format((float) $m->price, 2, ',', '.') : '€ 0,00';
                $liters = $m->liters !== null ? number_format((float) $m->liters, 2, ',', '.') . ' L' : '0,00 L';
                $note = $m->notes ?: '';
                $attachment = $m->photo_path
                    ? '<a href="' . e(asset('storage/' . $m->photo_path)) . '" target="_blank" class="text-primary-500">Stampa allegato</a>'
                    : 'N/D';

                return "<tr>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$date}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$vehicle}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$liters}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$price}</td>
                    <td class=\"px-2 py-1 text-sm\">" . e($note) . "</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$attachment}</td>
                </tr>";
            })->implode('');

        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="px-2 py-3 text-sm text-center text-gray-500">Nessun movimento</td></tr>';
        }

        return <<<HTML
            <div class="space-y-2">
                <div class="text-sm font-semibold">Stazione: {$label}</div>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-2 py-2 font-medium">Data</th>
                                <th class="px-2 py-2 font-medium">Veicolo</th>
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
