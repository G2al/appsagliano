<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithFinancialReportData;
use App\Support\FinancialReport;
use Filament\Tables;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class BestVehiclesByMarginTable extends BaseWidget
{
    use InteractsWithPageFilters;
    use InteractsWithFinancialReportData;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Veicoli migliori';

    protected function getTableQuery(): Builder
    {
        return FinancialReport::vehiclePerformanceQuery($this->resolveFinancialReportPeriod())
            ->orderByDesc('operating_margin')
            ->limit(5);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('plate')
                ->label('Veicolo')
                ->formatStateUsing(fn ($state, $record): string => $this->formatVehicleLabel($record)),
            Tables\Columns\TextColumn::make('operating_margin')
                ->label('Margine')
                ->formatStateUsing(fn ($state): string => $this->formatMoney($state))
                ->badge()
                ->color('success'),
        ];
    }

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }
}
