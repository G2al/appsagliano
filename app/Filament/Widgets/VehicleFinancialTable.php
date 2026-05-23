<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithFinancialReportData;
use App\Models\Maintenance;
use App\Models\Movement;
use App\Models\Vehicle;
use App\Support\FinancialReport;
use Filament\Tables;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class VehicleFinancialTable extends BaseWidget
{
    use InteractsWithPageFilters;
    use InteractsWithFinancialReportData;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Performance veicoli';

    protected function getTableQuery(): Builder
    {
        return FinancialReport::vehiclePerformanceQuery($this->resolveFinancialReportPeriod())
            ->orderByDesc('operating_margin')
            ->orderBy('vehicles.plate');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('plate')
                ->label('Veicolo')
                ->formatStateUsing(fn ($state, $record): string => $this->formatVehicleLabel($record))
                ->searchable(['vehicles.plate', 'vehicles.name']),
            Tables\Columns\TextColumn::make('revenues_ex_vat_total')
                ->label('Entrate nette')
                ->formatStateUsing(fn ($state): string => $this->formatMoney($state))
                ->sortable(),
            Tables\Columns\TextColumn::make('revenues_inc_vat_total')
                ->label('Entrate con IVA')
                ->formatStateUsing(fn ($state): string => $this->formatMoney($state))
                ->sortable(),
            Tables\Columns\TextColumn::make('refuels_total')
                ->label('Rifornimenti')
                ->formatStateUsing(fn ($state): string => $this->formatMoney($state))
                ->sortable(),
            Tables\Columns\TextColumn::make('maintenances_total')
                ->label('Manutenzioni')
                ->formatStateUsing(fn ($state): string => $this->formatMoney($state))
                ->sortable(),
            Tables\Columns\TextColumn::make('vehicle_total_costs')
                ->label('Costi veicolo')
                ->formatStateUsing(fn ($state): string => $this->formatMoney($state))
                ->sortable(),
            Tables\Columns\TextColumn::make('operating_margin')
                ->label('Margine')
                ->formatStateUsing(fn ($state): string => $this->formatMoney($state))
                ->badge()
                ->color(fn ($state): string => (float) ($state ?? 0) >= 0 ? 'success' : 'danger')
                ->sortable(),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('details')
                ->label('Dettaglio')
                ->icon('heroicon-o-list-bullet')
                ->modalHeading('Dettaglio economico veicolo')
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Chiudi')
                ->modalContent(fn (Vehicle $record): HtmlString => new HtmlString(
                    $this->renderVehicleDetails($record)
                )),
        ];
    }

    private function renderVehicleDetails(Vehicle $vehicle): string
    {
        $period = $this->resolveFinancialReportPeriod();

        $revenuesQuery = $vehicle->revenues();
        $period->applyToBuilder($revenuesQuery->getQuery(), 'date');
        $revenues = $revenuesQuery
            ->orderByDesc('date')
            ->limit(12)
            ->get();

        $movementsQuery = $vehicle->movements()->with('station');
        $period->applyToBuilder($movementsQuery->getQuery(), 'date');
        $movements = $movementsQuery
            ->orderByDesc('date')
            ->limit(12)
            ->get();

        $maintenancesQuery = $vehicle->maintenances()->with('supplier');
        $period->applyToBuilder($maintenancesQuery->getQuery(), 'date');
        $maintenances = $maintenancesQuery
            ->orderByDesc('date')
            ->limit(12)
            ->get();

        $revenueRows = $revenues->map(function ($revenue): string {
            return '<tr>'
                . '<td class="px-2 py-1 text-sm whitespace-nowrap">' . e($revenue->date?->format('d/m/Y') ?? '-') . '</td>'
                . '<td class="px-2 py-1 text-sm whitespace-nowrap">' . e($this->formatMoney($revenue->amount_ex_vat)) . '</td>'
                . '<td class="px-2 py-1 text-sm whitespace-nowrap">' . e(number_format((float) $revenue->vat_percentage, 2, ',', '.') . '%') . '</td>'
                . '<td class="px-2 py-1 text-sm whitespace-nowrap">' . e($this->formatMoney($revenue->amount_inc_vat)) . '</td>'
                . '</tr>';
        })->implode('');

        $movementRows = $movements->map(function (Movement $movement): string {
            return '<tr>'
                . '<td class="px-2 py-1 text-sm whitespace-nowrap">' . e($movement->date?->format('d/m/Y H:i') ?? '-') . '</td>'
                . '<td class="px-2 py-1 text-sm whitespace-nowrap">' . e($movement->station?->name ?? 'N/D') . '</td>'
                . '<td class="px-2 py-1 text-sm whitespace-nowrap">' . e($this->formatMoney($movement->price)) . '</td>'
                . '</tr>';
        })->implode('');

        $maintenanceRows = $maintenances->map(function (Maintenance $maintenance): string {
            return '<tr>'
                . '<td class="px-2 py-1 text-sm whitespace-nowrap">' . e($maintenance->date?->format('d/m/Y H:i') ?? '-') . '</td>'
                . '<td class="px-2 py-1 text-sm whitespace-nowrap">' . e($maintenance->supplier?->name ?? 'N/D') . '</td>'
                . '<td class="px-2 py-1 text-sm whitespace-nowrap">' . e($this->formatMoney($maintenance->price)) . '</td>'
                . '</tr>';
        })->implode('');

        $revenueRows = $revenueRows !== '' ? $revenueRows : '<tr><td colspan="4" class="px-2 py-3 text-sm text-center text-gray-500">Nessuna entrata registrata</td></tr>';
        $movementRows = $movementRows !== '' ? $movementRows : '<tr><td colspan="3" class="px-2 py-3 text-sm text-center text-gray-500">Nessun rifornimento registrato</td></tr>';
        $maintenanceRows = $maintenanceRows !== '' ? $maintenanceRows : '<tr><td colspan="3" class="px-2 py-3 text-sm text-center text-gray-500">Nessuna manutenzione registrata</td></tr>';

        return <<<HTML
            <div class="space-y-6">
                <div class="text-sm font-semibold">Veicolo: {$this->formatVehicleLabel($vehicle)}</div>

                <div class="grid gap-6 xl:grid-cols-3">
                    <div class="space-y-2">
                        <div class="text-sm font-medium">Entrate veicolo</div>
                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-2 py-2 font-medium">Data</th>
                                        <th class="px-2 py-2 font-medium">Netto IVA</th>
                                        <th class="px-2 py-2 font-medium">IVA</th>
                                        <th class="px-2 py-2 font-medium">Con IVA</th>
                                    </tr>
                                </thead>
                                <tbody>{$revenueRows}</tbody>
                            </table>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="text-sm font-medium">Rifornimenti</div>
                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-2 py-2 font-medium">Data</th>
                                        <th class="px-2 py-2 font-medium">Stazione</th>
                                        <th class="px-2 py-2 font-medium">Costo</th>
                                    </tr>
                                </thead>
                                <tbody>{$movementRows}</tbody>
                            </table>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="text-sm font-medium">Manutenzioni</div>
                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-2 py-2 font-medium">Data</th>
                                        <th class="px-2 py-2 font-medium">Fornitore</th>
                                        <th class="px-2 py-2 font-medium">Costo</th>
                                    </tr>
                                </thead>
                                <tbody>{$maintenanceRows}</tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        HTML;
    }
}
