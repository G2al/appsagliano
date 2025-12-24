<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use App\Filament\Widgets\RefuelsStats;
use App\Filament\Widgets\RefuelsByStationTable;
use App\Filament\Widgets\RefuelsByVehicleTable;
use App\Filament\Widgets\RefuelsByVehicleStationTable;
use App\Filament\Widgets\RefuelsListTable;

class ReportRefuels extends Page
{
    use HasFiltersForm;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Report rifornimenti';
    protected static ?string $navigationGroup = 'Report';
    protected static string $view = 'filament.pages.report-refuels';

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('start_date')
                    ->label('Dal')
                    ->default(now()->startOfMonth())
                    ->required(),
                DatePicker::make('end_date')
                    ->label('Al')
                    ->default(now())
                    ->required(),
            ]);
    }

    public function getWidgetData(): array
    {
        return [
            'filters' => $this->filters,
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RefuelsStats::class,
            RefuelsByVehicleStationTable::class,
            RefuelsByStationTable::class,
            RefuelsByVehicleTable::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            RefuelsListTable::class,
        ];
    }
}
