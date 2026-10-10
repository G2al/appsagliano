<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\TripsByDriverTable;
use App\Filament\Widgets\TripsByPlatformDriverTable;
use App\Filament\Widgets\TripsByVehicleDriverTable;
use App\Filament\Widgets\TripsByVehicleTable;
use App\Filament\Widgets\TripsListTable;
use App\Filament\Widgets\TripsStats;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Pages\Page;

class ReportTrips extends Page
{
    use HasFiltersAction;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Report viaggi';
    protected static ?string $title = 'Report viaggi';
    protected static ?string $navigationGroup = 'Report';
    protected static string $view = 'filament.pages.report-trips';
    protected static bool $shouldRegisterNavigation = true;

    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->form([
                    DatePicker::make('start_date')
                        ->label('Dal')
                        ->default(now()->startOfMonth())
                        ->required(),
                    DatePicker::make('end_date')
                        ->label('Al')
                        ->default(now())
                        ->required(),
                ]),
        ];
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
            TripsStats::class,
            TripsByVehicleTable::class,
            TripsByDriverTable::class,
            TripsByPlatformDriverTable::class,
            TripsByVehicleDriverTable::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            TripsListTable::class,
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->isAdmin() || $user->canAccessTripsArea();
    }
}
