<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithReportTableChecks;
use App\Filament\Widgets\Concerns\RendersTripDetails;
use App\Models\Trip;
use Filament\Tables;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class TripsByPlatformDriverTable extends BaseWidget
{
    use InteractsWithPageFilters;
    use InteractsWithReportTableChecks;
    use RendersTripDetails;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Viaggi per piattaforma e autista';

    protected function getTableQuery(): Builder
    {
        [$start, $end] = $this->getReportTableCheckDateRange();

        return Trip::query()
            ->selectRaw("
                MIN(trips.id) as id,
                platforms.id as platform_id,
                platforms.name as platform_name,
                users.id as user_id,
                users.name as user_name,
                users.surname as user_surname,
                COUNT(*) as trips_count,
                SUM(CASE WHEN trips.price IS NOT NULL THEN 1 ELSE 0 END) as certified_count,
                COALESCE(SUM(trips.price), 0) as price_total
            ")
            ->join('users', 'users.id', '=', 'trips.user_id')
            ->join('platforms', 'platforms.id', '=', 'trips.platform_id')
            ->whereBetween('trips.date', [$start, $end])
            ->groupBy('platforms.id', 'platforms.name', 'users.id', 'users.name', 'users.surname');
    }

    protected function getTableColumns(): array
    {
        return [
            $this->getReportTableCheckColumn(),
            Tables\Columns\TextColumn::make('platform_name')
                ->label('Piattaforma')
                ->searchable(['platforms.name'])
                ->sortable(),
            Tables\Columns\TextColumn::make('user_name')
                ->label('Autista')
                ->formatStateUsing(fn ($state, $record) => trim(($record->user_name ?? '') . ' ' . ($record->user_surname ?? '')))
                ->searchable(['users.name', 'users.surname']),
            Tables\Columns\TextColumn::make('trips_count')
                ->label('Viaggi')
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
        return "CONCAT('platform-user:', platforms.id, '-', users.id)";
    }

    protected function getReportTableRowKey(Model $record): string
    {
        return 'platform-user:' . (int) $record->platform_id . '-' . (int) $record->user_id;
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('dettagli')
                ->label('Vedi viaggi')
                ->icon('heroicon-o-list-bullet')
                ->modalHeading('Viaggi piattaforma / autista')
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Chiudi')
                ->modalContent(function ($record) {
                    [$start, $end] = $this->getReportTableCheckDateRange();

                    $query = Trip::query()
                        ->where('user_id', (int) $record->user_id)
                        ->where('platform_id', (int) $record->platform_id)
                        ->whereBetween('date', [$start, $end]);

                    return new HtmlString($this->renderTripDetails(
                        $query,
                        ($record->platform_name ?? 'N/D') . ' — ' . trim(($record->user_name ?? '') . ' ' . ($record->user_surname ?? ''))
                    ));
                }),
        ];
    }
}
