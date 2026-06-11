<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithReportTableChecks;
use App\Models\Movement;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class RefuelsByVehicleTable extends BaseWidget
{
    use InteractsWithPageFilters;
    use InteractsWithReportTableChecks;

    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Rifornimenti per veicolo';

    protected function getTableQuery(): Builder
    {
        [$start, $end] = $this->resolveDateRange();

        return Movement::query()
            ->selectRaw("
                MIN(movements.id) as id,
                vehicles.id as vehicle_id,
                vehicles.plate,
                vehicles.name,
                SUM(movements.price) as price_total,
                SUM(movements.liters) as liters_total,
                COUNT(*) as movements_count
            ")
            ->join('vehicles', 'vehicles.id', '=', 'movements.vehicle_id')
            ->whereBetween('movements.date', [$start, $end])
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

    protected function getReportTableRowKey(Model $record): string
    {
        return 'vehicle:' . (int) $record->vehicle_id;
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('dettagli')
                ->label('Vedi movimenti')
                ->icon('heroicon-o-list-bullet')
                ->modalHeading('Movimenti del veicolo')
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Chiudi')
                ->modalContent(fn ($record) => new HtmlString(
                    $this->renderDetailsForVehicle(
                        (int) $record->vehicle_id,
                        trim(($record->plate ? $record->plate . ' - ' : '') . ($record->name ?? ''))
                    )
                )),
        ];
    }

    private function renderDetailsForVehicle(int $vehicleId, string $label): string
    {
        [$start, $end] = $this->resolveDateRange();

        $movements = Movement::with(['vehicle', 'station'])
            ->where('vehicle_id', $vehicleId)
            ->whereBetween('date', [$start, $end])
            ->orderByDesc('date')
            ->limit(50)
            ->get();

        $creditCount = $movements->where('is_voucher', false)->count();
        $voucherCount = $movements->where('is_voucher', true)->count();
        $totalCount = $movements->count();

        $rows = $movements
            ->map(function (Movement $movement) {
                $date = $movement->date?->format('d/m/Y H:i') ?? 'N/D';
                $station = $movement->station?->name ?? 'Stazione';
                $liters = $movement->liters !== null ? number_format((float) $movement->liters, 2, ',', '.') . ' L' : '0,00 L';
                $price = $movement->price !== null ? 'EUR ' . number_format((float) $movement->price, 2, ',', '.') : 'EUR 0,00';
                $paymentLabel = $movement->is_voucher ? 'Buono' : 'Credito';
                $paymentClasses = $movement->is_voucher
                    ? 'bg-warning-100 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300'
                    : 'bg-success-100 text-success-700 dark:bg-success-500/10 dark:text-success-300';
                $paymentBadge = '<span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ' . $paymentClasses . '">' . $paymentLabel . '</span>';
                $note = $movement->notes ?: '';
                $attachment = $movement->photo_path
                    ? '<a href="' . e(asset('storage/' . $movement->photo_path)) . '" target="_blank" class="text-primary-500">Stampa allegato</a>'
                    : 'N/D';

                return "<tr>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$date}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$paymentBadge}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$station}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$liters}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$price}</td>
                    <td class=\"px-2 py-1 text-sm\">" . e($note) . "</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$attachment}</td>
                </tr>";
            })
            ->implode('');

        if ($rows === '') {
            $rows = '<tr><td colspan="7" class="px-2 py-3 text-sm text-center text-gray-500">Nessun movimento</td></tr>';
        }

        return <<<HTML
            <div class="space-y-2">
                <div class="text-sm font-semibold">Veicolo: {$label}</div>
                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">Totale: {$totalCount}</span>
                    <span class="inline-flex items-center rounded-md bg-success-100 px-2 py-1 font-medium text-success-700 dark:bg-success-500/10 dark:text-success-300">Credito: {$creditCount}</span>
                    <span class="inline-flex items-center rounded-md bg-warning-100 px-2 py-1 font-medium text-warning-700 dark:bg-warning-500/10 dark:text-warning-300">Buono: {$voucherCount}</span>
                </div>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-2 py-2 font-medium">Data</th>
                                <th class="px-2 py-2 font-medium">Pagamento</th>
                                <th class="px-2 py-2 font-medium">Stazione</th>
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
