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

class TripsByDriverTable extends BaseWidget
{
    use InteractsWithPageFilters;
    use InteractsWithReportTableChecks;
    use RendersAttachmentLightbox;
    use RendersTripDetails;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Viaggi per autista';

    protected function getTableQuery(): Builder
    {
        [$start, $end] = $this->getReportTableCheckDateRange();

        return Trip::query()
            ->selectRaw("
                MIN(trips.id) as id,
                users.id as user_id,
                users.name as user_name,
                users.surname as user_surname,
                COUNT(*) as trips_count,
                COALESCE(SUM(trips.distance_km), 0) as distance_total,
                COALESCE(SUM(trips.price), 0) as price_total
            ")
            ->join('users', 'users.id', '=', 'trips.user_id')
            ->whereBetween('trips.date', [$start, $end])
            ->groupBy('users.id', 'users.name', 'users.surname');
    }

    protected function getTableColumns(): array
    {
        return [
            $this->getReportTableCheckColumn(),
            Tables\Columns\TextColumn::make('user_name')
                ->label('Autista')
                ->formatStateUsing(fn ($state, $record) => trim(($record->user_name ?? '') . ' ' . ($record->user_surname ?? '')))
                ->searchable(['users.name', 'users.surname']),
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

    protected function getReportTableRowKeySql(): string
    {
        return "CONCAT('user:', users.id)";
    }

    protected function getReportTableRowKey(Model $record): string
    {
        return 'user:' . (int) $record->user_id;
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('dettagli')
                ->label('Vedi viaggi')
                ->icon('heroicon-o-list-bullet')
                ->modalHeading('Viaggi dell\'autista')
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Chiudi')
                ->modalContent(function ($record) {
                    [$start, $end] = $this->getReportTableCheckDateRange();

                    $query = Trip::query()
                        ->where('user_id', (int) $record->user_id)
                        ->whereBetween('date', [$start, $end]);

                    return new HtmlString($this->renderTripDetails(
                        $query,
                        'Autista: ' . trim(($record->user_name ?? '') . ' ' . ($record->user_surname ?? ''))
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
                        trim(($r->user_name ?? '') . ' ' . ($r->user_surname ?? '')),
                        (int) $r->trips_count,
                        (float) $r->distance_total,
                        (float) $r->price_total,
                    ]);

                    $spreadsheet = GenericExcelExporter::build('Viaggi per autista', ['Autista', 'Viaggi', 'Km percorsi', 'Totale (€)'], $rows);

                    return GenericExcelExporter::stream($spreadsheet, 'viaggi-per-autista.xlsx');
                }),
        ];
    }
}
