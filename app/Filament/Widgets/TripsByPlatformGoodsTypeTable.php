<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithReportTableChecks;
use App\Filament\Widgets\Concerns\RendersTripDetails;
use App\Models\Trip;
use App\Support\GenericExcelExporter;
use Filament\Tables;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class TripsByPlatformGoodsTypeTable extends BaseWidget
{
    use InteractsWithPageFilters;
    use InteractsWithReportTableChecks;
    use RendersTripDetails;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Bordero per piattaforma e dicitura';

    protected function getTableQuery(): Builder
    {
        [$start, $end] = $this->getReportTableCheckDateRange();

        return Trip::query()
            ->selectRaw("
                MIN(trips.id) as id,
                platforms.id as platform_id,
                platforms.name as platform_name,
                goods_types.id as goods_type_id,
                goods_types.name as goods_type_name,
                COUNT(*) as trips_count,
                SUM(CASE WHEN trips.price IS NOT NULL THEN 1 ELSE 0 END) as certified_count,
                COALESCE(SUM(trips.price), 0) as price_total
            ")
            ->join('filiali', 'filiali.id', '=', 'trips.filiale_id')
            ->join('platforms', 'platforms.id', '=', 'filiali.platform_id')
            ->join('goods_types', 'goods_types.id', '=', 'filiali.goods_type_id')
            ->whereBetween('trips.date', [$start, $end])
            ->groupBy('platforms.id', 'platforms.name', 'goods_types.id', 'goods_types.name');
    }

    protected function getTableColumns(): array
    {
        return [
            $this->getReportTableCheckColumn(),
            Tables\Columns\TextColumn::make('platform_name')
                ->label('Piattaforma')
                ->searchable(['platforms.name'])
                ->sortable(),
            Tables\Columns\TextColumn::make('goods_type_name')
                ->label('Dicitura')
                ->badge()
                ->searchable(['goods_types.name'])
                ->sortable(),
            Tables\Columns\TextColumn::make('trips_count')
                ->label('Record')
                ->sortable(),
            Tables\Columns\TextColumn::make('certified_count')
                ->label('Certificati')
                ->sortable(),
            Tables\Columns\TextColumn::make('pending_count')
                ->label('Da certificare')
                ->state(fn ($record) => (int) $record->trips_count - (int) $record->certified_count),
            Tables\Columns\TextColumn::make('price_total')
                ->label('Totale certificato')
                ->money('EUR', true)
                ->sortable(),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            $this->getReportTableCheckedFilter(),
        ];
    }

    protected function getDefaultTableSortColumn(): ?string
    {
        return 'platform_name';
    }

    protected function getDefaultTableSortDirection(): ?string
    {
        return 'asc';
    }

    protected function getReportTableRowKeySql(): string
    {
        return "CONCAT('platform-goodstype:', platforms.id, '-', goods_types.id)";
    }

    protected function getReportTableRowKey(Model $record): string
    {
        return 'platform-goodstype:' . (int) $record->platform_id . '-' . (int) $record->goods_type_id;
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('dettagli')
                ->label('Vedi record')
                ->icon('heroicon-o-list-bullet')
                ->modalHeading('Bordero piattaforma / dicitura')
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Chiudi')
                ->modalContent(function ($record) {
                    [$start, $end] = $this->getReportTableCheckDateRange();

                    $query = Trip::query()
                        ->whereHas('filiale', fn ($q) => $q
                            ->where('platform_id', (int) $record->platform_id)
                            ->where('goods_type_id', (int) $record->goods_type_id))
                        ->whereBetween('date', [$start, $end]);

                    return new HtmlString($this->renderTripDetails(
                        $query,
                        ($record->platform_name ?? 'N/D') . ' — ' . ($record->goods_type_name ?? 'N/D'),
                        $this->getReportTableRowKey($record)
                    ));
                }),
            Tables\Actions\Action::make('export_excel')
                ->label('Esporta Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function ($record) {
                    [$start, $end] = $this->getReportTableCheckDateRange();

                    $trips = Trip::query()
                        ->whereHas('filiale', fn ($q) => $q
                            ->where('platform_id', (int) $record->platform_id)
                            ->where('goods_type_id', (int) $record->goods_type_id))
                        ->whereBetween('date', [$start, $end])
                        ->with(['user', 'vehicle', 'filiale'])
                        ->orderBy('date')
                        ->get();

                    $rows = $trips->map(fn (Trip $trip) => [
                        $trip->date?->format('d/m/Y H:i'),
                        $trip->delivery_note_number,
                        $trip->filiale ? "{$trip->filiale->codice} - {$trip->filiale->name}" : 'N/D',
                        $trip->user?->full_name,
                        trim(($trip->vehicle?->plate ? $trip->vehicle->plate . ' - ' : '') . ($trip->vehicle?->name ?? '')),
                        $trip->scarichi,
                        $trip->price !== null ? (float) $trip->price : null,
                    ]);

                    $spreadsheet = GenericExcelExporter::build(
                        'Bordero piattaforma-dicitura',
                        ['Data', 'Numero bordero', 'Filiale', 'Autista', 'Veicolo', 'Scarichi', 'Prezzo (€)'],
                        $rows
                    );

                    return GenericExcelExporter::stream($spreadsheet, 'bordero-per-piattaforma-dicitura.xlsx');
                }),
        ];
    }
}
