<?php

namespace App\Filament\Widgets;

use App\Models\Maintenance;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class MaintenancesByVehicleTable extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Manutenzioni per veicolo';

    protected function getTableQuery(): Builder
    {
        [$start, $end, $vehicleIdFilter, $supplierIdFilter] = $this->resolveFilters();

        $query = Maintenance::query()
            ->selectRaw("
                MIN(maintenances.id) as id,
                vehicles.id as vehicle_id,
                vehicles.plate,
                vehicles.name,
                SUM(maintenances.price) as price_total,
                COUNT(*) as maintenances_count
            ")
            ->join('vehicles', 'vehicles.id', '=', 'maintenances.vehicle_id')
            ->whereBetween('maintenances.date', [$start, $end])
            ->groupBy('vehicles.id', 'vehicles.plate', 'vehicles.name')
            ->orderByDesc('price_total');

        if ($vehicleIdFilter) {
            $query->where('maintenances.vehicle_id', $vehicleIdFilter);
        }
        if ($supplierIdFilter) {
            $query->where('maintenances.supplier_id', $supplierIdFilter);
        }

        return $query;
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('plate')
                ->label('Veicolo')
                ->formatStateUsing(fn ($state, $record) => trim(($record->plate ? $record->plate . ' - ' : '') . ($record->name ?? '')))
                ->searchable(['plate', 'name']),
            Tables\Columns\TextColumn::make('price_total')
                ->label('Spesa')
                ->money('EUR', true)
                ->sortable(),
            Tables\Columns\TextColumn::make('maintenances_count')
                ->label('Interventi')
                ->sortable(),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('dettagli')
                ->label('Vedi interventi')
                ->icon('heroicon-o-list-bullet')
                ->modalHeading('Interventi del veicolo')
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
        [$start, $end, $_vehicleFilter, $supplierFilter] = $this->resolveFilters();

        $query = Maintenance::with(['supplier'])
            ->where('vehicle_id', $vehicleId)
            ->whereBetween('date', [$start, $end])
            ->orderByDesc('date');

        if ($supplierFilter) {
            $query->where('supplier_id', $supplierFilter);
        }

        $rows = $query->limit(50)->get()->map(function (Maintenance $m) {
            $date = $m->date?->format('d/m/Y H:i') ?? 'N/D';
            $supplier = $m->supplier?->name ?? 'Fornitore';
            $price = $m->price !== null ? '€ ' . number_format((float) $m->price, 2, ',', '.') : '€ 0,00';
            $note = $m->notes ?: '';
            $attachment = $m->attachment_url
                ? '<a href="' . e(route('maintenances.attachment', $m)) . '" target="_blank" class="text-primary-500">Stampa allegato</a>'
                : 'N/D';

            return "<tr>
                <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$date}</td>
                <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$supplier}</td>
                <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$price}</td>
                <td class=\"px-2 py-1 text-sm\">" . e($note) . "</td>
                <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$attachment}</td>
            </tr>";
        })->implode('');

        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="px-2 py-3 text-sm text-center text-gray-500">Nessun intervento</td></tr>';
        }

        return <<<HTML
            <div class="space-y-2">
                <div class="text-sm font-semibold">Veicolo: {$label}</div>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-2 py-2 font-medium">Data</th>
                                <th class="px-2 py-2 font-medium">Fornitore</th>
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

    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon, 2: ?int, 3: ?int}
     */
    private function resolveFilters(): array
    {
        $filters = $this->tableFilters ?? [];
        $dateFilter = $filters['date_range'] ?? [];
        if (is_array($dateFilter) && array_key_exists('value', $dateFilter)) {
            $dateFilter = $dateFilter['value'] ?? [];
        }

        $start = Carbon::parse($dateFilter['start'] ?? now()->startOfMonth())->startOfDay();
        $end = Carbon::parse($dateFilter['end'] ?? now())->endOfDay();

        $vehicleFilter = $filters['vehicle_id'] ?? null;
        $vehicleId = is_array($vehicleFilter) ? ($vehicleFilter['value'] ?? null) : $vehicleFilter;

        $supplierFilter = $filters['supplier_id'] ?? null;
        $supplierId = is_array($supplierFilter) ? ($supplierFilter['value'] ?? null) : $supplierFilter;

        return [
            $start,
            $end,
            $vehicleId ? (int) $vehicleId : null,
            $supplierId ? (int) $supplierId : null,
        ];
    }
}
