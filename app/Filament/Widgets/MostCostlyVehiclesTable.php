<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithFinancialReportData;
use App\Support\FinancialReport;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class MostCostlyVehiclesTable extends BaseWidget
{
    use InteractsWithPageFilters;
    use InteractsWithFinancialReportData;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Veicoli piu costosi';

    protected function getTableQuery(): Builder
    {
        return FinancialReport::vehiclePerformanceQuery($this->resolveFinancialReportPeriod())
            ->orderByDesc('vehicle_total_costs')
            ->limit(5);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('plate')
                ->label('Veicolo')
                ->formatStateUsing(fn ($state, $record): string => $this->formatVehicleLabel($record)),
            Tables\Columns\TextColumn::make('vehicle_total_costs')
                ->label('Costi')
                ->formatStateUsing(fn ($state): string => $this->formatMoney($state))
                ->badge()
                ->color('danger'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false);
    }
}
