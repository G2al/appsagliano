<?php

namespace App\Filament\Widgets;

use App\Models\Movement;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RefuelsStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        [$start, $end] = $this->resolveDateRange();

        $totals = Movement::query()
            ->whereBetween('date', [$start, $end])
            ->selectRaw('
                COUNT(*) as movements_count,
                COUNT(DISTINCT station_id) as stations_count,
                COALESCE(SUM(price), 0) as price_total
            ')
            ->first();

        $priceTotal = (float) ($totals?->price_total ?? 0);
        $count = (int) ($totals?->movements_count ?? 0);
        $stations = (int) ($totals?->stations_count ?? 0);

        return [
            Stat::make('Totale speso', '€ ' . number_format($priceTotal, 2, ',', '.'))
                ->icon('heroicon-o-currency-euro'),
            Stat::make('Movimenti', number_format($count, 0, ',', '.'))
                ->icon('heroicon-o-clipboard-document'),
            Stat::make('Stazioni', number_format($stations, 0, ',', '.'))
                ->icon('heroicon-o-building-storefront'),
        ];
    }

    private function resolveDateRange(): array
    {
        $filters = $this->filters ?? [];
        $startRaw = $filters['start_date'] ?? null;
        $endRaw = $filters['end_date'] ?? null;

        $start = Carbon::parse($startRaw ?: now()->startOfMonth())->startOfDay();
        $end = Carbon::parse($endRaw ?: now())->endOfDay();

        return [$start, $end];
    }
}
