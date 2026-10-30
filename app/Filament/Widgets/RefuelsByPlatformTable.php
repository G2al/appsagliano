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

class RefuelsByPlatformTable extends BaseWidget
{
    use InteractsWithPageFilters;
    use InteractsWithReportTableChecks;

    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Rifornimenti per piattaforma';

    protected function getTableQuery(): Builder
    {
        [$start, $end] = $this->resolveDateRange();

        return Movement::query()
            ->selectRaw("
                MIN(movements.id) as id,
                platforms.id as platform_id,
                platforms.name,
                SUM(movements.price) as price_total,
                SUM(movements.liters) as liters_total,
                COUNT(*) as movements_count
            ")
            ->leftJoin('platforms', 'platforms.id', '=', 'movements.platform_id')
            ->whereBetween('movements.date', [$start, $end])
            ->groupBy('platforms.id', 'platforms.name');
    }

    protected function getTableColumns(): array
    {
        return [
            $this->getReportTableCheckColumn(),
            Tables\Columns\TextColumn::make('name')
                ->label('Piattaforma')
                ->placeholder('N/D')
                ->searchable(['platforms.name']),
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

    protected function getTableFilters(): array
    {
        return [
            $this->getReportTableCheckedFilter(),
        ];
    }

    protected function getReportTableRowKeySql(): string
    {
        return "CONCAT('platform:', COALESCE(platforms.id, 0))";
    }

    protected function getReportTableRowKey(Model $record): string
    {
        return 'platform:' . (int) $record->platform_id;
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('dettagli')
                ->label('Vedi movimenti')
                ->icon('heroicon-o-list-bullet')
                ->modalHeading('Movimenti della piattaforma')
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Chiudi')
                ->modalContent(fn ($record) => new HtmlString(
                    $this->renderDetailsForPlatform($record->platform_id !== null ? (int) $record->platform_id : null, $record->name ?? 'N/D')
                )),
        ];
    }

    private function renderDetailsForPlatform(?int $platformId, string $label): string
    {
        [$start, $end] = $this->resolveDateRange();

        $rows = Movement::with(['vehicle', 'platform'])
            ->when(
                $platformId !== null,
                fn (Builder $query) => $query->where('platform_id', $platformId),
                fn (Builder $query) => $query->whereNull('platform_id')
            )
            ->whereBetween('date', [$start, $end])
            ->orderByDesc('date')
            ->limit(50)
            ->get()
            ->map(function (Movement $movement) {
                $date = $movement->date?->format('d/m/Y H:i') ?? 'N/D';
                $vehicle = trim(($movement->vehicle?->plate ? $movement->vehicle->plate . ' - ' : '') . ($movement->vehicle?->name ?? 'Veicolo'));
                $price = $movement->price !== null ? 'EUR ' . number_format((float) $movement->price, 2, ',', '.') : 'EUR 0,00';
                $liters = $movement->liters !== null ? number_format((float) $movement->liters, 2, ',', '.') . ' L' : '0,00 L';
                $paymentLabel = $movement->is_voucher ? 'Buono' : 'Credito';
                $note = $movement->notes ?: '';
                $attachment = $movement->photo_path
                    ? '<a href="' . e(asset('storage/' . $movement->photo_path)) . '" target="_blank" class="text-primary-500">Stampa allegato</a>'
                    : 'N/D';

                return "<tr>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$date}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$vehicle}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$paymentLabel}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$liters}</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$price}</td>
                    <td class=\"px-2 py-1 text-sm\">" . e($note) . "</td>
                    <td class=\"px-2 py-1 whitespace-nowrap text-sm\">{$attachment}</td>
                </tr>";
            })->implode('');

        if ($rows === '') {
            $rows = '<tr><td colspan="7" class="px-2 py-3 text-sm text-center text-gray-500">Nessun movimento</td></tr>';
        }

        return <<<HTML
            <div class="space-y-2">
                <div class="text-sm font-semibold">Piattaforma: {$label}</div>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-2 py-2 font-medium">Data</th>
                                <th class="px-2 py-2 font-medium">Veicolo</th>
                                <th class="px-2 py-2 font-medium">Pagamento</th>
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
