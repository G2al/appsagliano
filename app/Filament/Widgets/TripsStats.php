<?php

namespace App\Filament\Widgets;

use App\Models\Trip;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TripsStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $filters = $this->filters ?? [];
        $start = Carbon::parse($filters['start_date'] ?? now()->startOfMonth())->startOfDay();
        $end = Carbon::parse($filters['end_date'] ?? now())->endOfDay();

        $totals = Trip::query()
            ->whereBetween('date', [$start, $end])
            ->selectRaw('
                COUNT(*) as trips_count,
                COALESCE(SUM(distance_km), 0) as distance_total,
                COALESCE(SUM(price), 0) as price_total
            ')
            ->first();

        $count = (int) ($totals?->trips_count ?? 0);
        $distanceTotal = (float) ($totals?->distance_total ?? 0);
        $priceTotal = (float) ($totals?->price_total ?? 0);

        return [
            Stat::make('Viaggi', number_format($count, 0, ',', '.'))
                ->icon('heroicon-o-truck'),
            Stat::make('Km percorsi', number_format($distanceTotal, 0, ',', '.') . ' km')
                ->icon('heroicon-o-map'),
            Stat::make('Totale', '€ ' . number_format($priceTotal, 2, ',', '.'))
                ->icon('heroicon-o-currency-euro'),
        ];
    }
}
