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
                COALESCE(SUM(CASE WHEN price IS NOT NULL THEN 1 ELSE 0 END), 0) as certified_count,
                COALESCE(SUM(price), 0) as price_total
            ')
            ->first();

        $count = (int) ($totals?->trips_count ?? 0);
        $certified = (int) ($totals?->certified_count ?? 0);
        $priceTotal = (float) ($totals?->price_total ?? 0);

        return [
            Stat::make('Viaggi', number_format($count, 0, ',', '.'))
                ->icon('heroicon-o-truck'),
            Stat::make('Certificati', number_format($certified, 0, ',', '.'))
                ->icon('heroicon-o-check-badge'),
            Stat::make('Da certificare', number_format($count - $certified, 0, ',', '.'))
                ->icon('heroicon-o-clock'),
            Stat::make('Totale certificato', '€ ' . number_format($priceTotal, 2, ',', '.'))
                ->icon('heroicon-o-currency-euro'),
        ];
    }
}
