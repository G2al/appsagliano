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

class TripsByFilialeDriverTable extends BaseWidget
{
    use InteractsWithPageFilters;
    use InteractsWithReportTableChecks;
    use RendersTripDetails;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Bordero per filiale e autista';

    protected function getTableQuery(): Builder
    {
        [$start, $end] = $this->getReportTableCheckDateRange();

        return Trip::query()
            ->selectRaw("
                MIN(trips.id) as id,
                filiali.id as filiale_id,
                filiali.name as filiale_name,
                filiali.codice as filiale_codice,
                users.id as user_id,
                users.name as user_name,
                users.surname as user_surname,
                COUNT(*) as trips_count,
                SUM(CASE WHEN trips.price IS NOT NULL THEN 1 ELSE 0 END) as certified_count,
                COALESCE(SUM(trips.price), 0) as price_total
            ")
            ->join('users', 'users.id', '=', 'trips.user_id')
            ->join('filiali', 'filiali.id', '=', 'trips.filiale_id')
            ->whereBetween('trips.date', [$start, $end])
            ->groupBy('filiali.id', 'filiali.name', 'filiali.codice', 'users.id', 'users.name', 'users.surname');
    }

    protected function getTableColumns(): array
    {
        return [
            $this->getReportTableCheckColumn(),
            Tables\Columns\TextColumn::make('filiale_name')
                ->label('Filiale')
                ->formatStateUsing(fn ($state, $record) => "{$record->filiale_codice} - {$record->filiale_name}")
                ->searchable(['filiali.name', 'filiali.codice'])
                ->sortable(),
            Tables\Columns\TextColumn::make('user_name')
                ->label('Autista')
                ->formatStateUsing(fn ($state, $record) => trim(($record->user_name ?? '') . ' ' . ($record->user_surname ?? '')))
                ->searchable(['users.name', 'users.surname']),
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
        return 'filiale_name';
    }

    protected function getDefaultTableSortDirection(): ?string
    {
        return 'asc';
    }

    protected function getReportTableRowKeySql(): string
    {
        return "CONCAT('filiale-user:', filiali.id, '-', users.id)";
    }

    protected function getReportTableRowKey(Model $record): string
    {
        return 'filiale-user:' . (int) $record->filiale_id . '-' . (int) $record->user_id;
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('dettagli')
                ->label('Vedi record')
                ->icon('heroicon-o-list-bullet')
                ->modalHeading('Bordero filiale / autista')
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Chiudi')
                ->modalContent(function ($record) {
                    [$start, $end] = $this->getReportTableCheckDateRange();

                    $query = Trip::query()
                        ->where('user_id', (int) $record->user_id)
                        ->where('filiale_id', (int) $record->filiale_id)
                        ->whereBetween('date', [$start, $end]);

                    return new HtmlString($this->renderTripDetails(
                        $query,
                        "{$record->filiale_codice} - {$record->filiale_name}" . ' — ' . trim(($record->user_name ?? '') . ' ' . ($record->user_surname ?? ''))
                    ));
                }),
        ];
    }
}
