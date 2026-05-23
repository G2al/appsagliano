<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BestVehiclesByMarginTable;
use App\Filament\Widgets\FinancialOverviewStats;
use App\Filament\Widgets\MostCostlyVehiclesTable;
use App\Filament\Widgets\VehicleFinancialTable;
use App\Filament\Widgets\WorstVehiclesByMarginTable;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;

class ReportGeneral extends Page
{
    use HasFiltersForm;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Report generale';
    protected static ?string $navigationGroup = 'Report';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.report-general';

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('period_preset')
                    ->label('Periodo')
                    ->options([
                        'current_month' => 'Questo mese',
                        'last_month' => 'Mese scorso',
                        'total' => 'Totale',
                    ])
                    ->default('current_month')
                    ->live()
                    ->afterStateHydrated(function (Set $set, ?string $state): void {
                        self::applyPreset($set, $state);
                    })
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        self::applyPreset($set, $state);
                    }),
                DatePicker::make('start_date')
                    ->label('Dal')
                    ->default(now()->startOfMonth()->toDateString())
                    ->disabled(fn (Get $get): bool => $get('period_preset') === 'total')
                    ->live(),
                DatePicker::make('end_date')
                    ->label('Al')
                    ->default(now()->toDateString())
                    ->disabled(fn (Get $get): bool => $get('period_preset') === 'total')
                    ->live(),
            ])
            ->columns(3);
    }

    public function getWidgetData(): array
    {
        return [
            'filters' => $this->filters,
        ];
    }

    public function getHeader(): ?View
    {
        return view('filament.pages.report-general-header');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FinancialOverviewStats::class,
            BestVehiclesByMarginTable::class,
            WorstVehiclesByMarginTable::class,
            MostCostlyVehiclesTable::class,
            VehicleFinancialTable::class,
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    private static function applyPreset(Set $set, ?string $preset): void
    {
        if ($preset === 'total') {
            $set('start_date', null);
            $set('end_date', null);

            return;
        }

        if ($preset === 'last_month') {
            $start = now()->subMonthNoOverflow()->startOfMonth();

            $set('start_date', $start->toDateString());
            $set('end_date', $start->copy()->endOfMonth()->toDateString());

            return;
        }

        $set('start_date', now()->startOfMonth()->toDateString());
        $set('end_date', now()->toDateString());
    }
}
