<?php

namespace App\Filament\Pages;

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
    protected static bool $shouldRegisterNavigation = false;

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

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Widgets\MaintenancesListTable::class,
        ];
    }
}
