<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithReportTableChecks;
use App\Filament\Widgets\Concerns\RendersAttachmentLightbox;
use App\Filament\Widgets\Concerns\RendersTripDetails;
use App\Models\Trip;
use App\Support\GenericExcelExporter;
use Filament\Tables;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class TripsByPlatformTable extends BaseWidget
{
    use InteractsWithPageFilters;
    use InteractsWithReportTableChecks;
    use RendersAttachmentLightbox;
    use RendersTripDetails;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Viaggi per piattaforma';

    protected function getTableQuery(): Builder
    {
        [$start, $end] = $this->getReportTableCheckDateRange();

        return Trip::query()
            ->selectRaw("
                MIN(trips.id) as id,
                platforms.id as platform_id,
                platforms.name as platform_name,
                COUNT(*) as trips_count,
                COALESCE(SUM(trips.distance_km), 0) as distance_total,
                COALESCE(SUM(trips.price), 0) as price_total
            ")
            ->join('platforms', 'platforms.id', '=', 'trips.platform_id')
            ->whereBetween('trips.date', [$start, $end])
            ->groupBy('platforms.id', 'platforms.name');
    }

    protected function getTableColumns(): array
    {
        return [
            $this->getReportTableCheckColumn(),
            Tables\Columns\TextColumn::make('platform_name')
                ->label('Piattaforma')
                ->searchable(['platforms.name'])
                ->sortable(),
            Tables\Columns\TextColumn::make('trips_count')
                ->label('Viaggi')
                ->sortable(),
            Tables\Columns\TextColumn::make('distance_total')
                ->label('Km percorsi')
                ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', '.') . ' km')
                ->sortable(),
            Tables\Columns\TextColumn::make('price_total')
                ->label('Totale')
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
        return "CONCAT('platform:', platforms.id)";
    }

    protected function getReportTableRowKey(Model $record): string
    {
        return 'platform:' . (int) $record->platform_id;
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('dettagli')
                ->label('Vedi viaggi')
                ->icon('heroicon-o-list-bullet')
                ->modalHeading('Viaggi della piattaforma')
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Chiudi')
                ->modalContent(function ($record) {
                    [$start, $end] = $this->getReportTableCheckDateRange();

                    $query = Trip::query()
                        ->where('platform_id', (int) $record->platform_id)
                        ->whereBetween('date', [$start, $end]);

                    return new HtmlString($this->renderTripDetails(
                        $query,
                        'Piattaforma: ' . ($record->platform_name ?? 'N/D')
                    ));
                }),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Tables\Actions\Action::make('export_excel')
                ->label('Esporta Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $rows = $this->getTableQuery()->get()->map(fn ($r) => [
                        $r->platform_name,
                        (int) $r->trips_count,
                        (float) $r->distance_total,
                        (float) $r->price_total,
                    ]);

                    $spreadsheet = GenericExcelExporter::build('Viaggi per piattaforma', ['Piattaforma', 'Viaggi', 'Km percorsi', 'Totale (€)'], $rows);

                    return GenericExcelExporter::stream($spreadsheet, 'viaggi-per-piattaforma.xlsx');
                }),
        ];
    }
}
