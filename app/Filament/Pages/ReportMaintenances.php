<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\MaintenancesStats;
use App\Filament\Widgets\MaintenancesBySupplierTable;
use App\Filament\Widgets\MaintenancesByVehicleTable;
use App\Filament\Widgets\MaintenancesByVehicleSupplierTable;
use App\Filament\Widgets\MaintenancesListTable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;

class ReportMaintenances extends Page
{
    use HasFiltersForm;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Report manutenzioni';
    protected static ?string $navigationGroup = 'Report';
    protected static string $view = 'filament.pages.report-maintenances';
    protected static bool $shouldRegisterNavigation = true;

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('start_date')
                    ->label('Dal')
                    ->default(now()->startOfMonth())
                    ->required()
                    ->live(),
                DatePicker::make('end_date')
                    ->label('Al')
                    ->default(now())
                    ->required()
                    ->live(),
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
            MaintenancesStats::class,
            MaintenancesByVehicleSupplierTable::class,
            MaintenancesBySupplierTable::class,
            MaintenancesByVehicleTable::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            MaintenancesListTable::class,
        ];
    }
}
