<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Filament\Widgets\MaintenancesStats;
use App\Filament\Widgets\MaintenancesBySupplierTable;
use App\Filament\Widgets\MaintenancesByVehicleTable;
use App\Filament\Widgets\MaintenancesByVehicleSupplierTable;
use App\Filament\Widgets\MaintenancesListTable;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;

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
                Actions::make([
                    FormAction::make('preset_last_month')
                        ->label('Mese scorso')
                        ->color('gray')
                        ->action(function (Set $set): void {
                            $lastMonth = now()->subMonthNoOverflow();

                            $set('start_date', $lastMonth->copy()->startOfMonth()->toDateString());
                            $set('end_date', $lastMonth->copy()->endOfMonth()->toDateString());
                        }),
                ])
                    ->alignment(Alignment::Start)
                    ->columnSpanFull()
                    ->fullWidth()
                    ->key('report_maintenances_last_month_preset'),
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

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->isAdmin() || $user->canAccessMaintenanceArea();
    }
}
