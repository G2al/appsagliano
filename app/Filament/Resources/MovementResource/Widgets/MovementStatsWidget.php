<?php

namespace App\Filament\Resources\MovementResource\Widgets;

use App\Models\Movement;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MovementStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $aggregates = Movement::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(liters), 0) as liters_total')
            ->selectRaw('COALESCE(SUM(price), 0) as price_total')
            ->selectRaw('COALESCE(SUM(adblue), 0) as adblue_total')
            ->first();

        $total = (int) ($aggregates->total ?? 0);
        $litersTotal = (float) ($aggregates->liters_total ?? 0);
        $priceTotal = (float) ($aggregates->price_total ?? 0);
        $adblueTotal = (float) ($aggregates->adblue_total ?? 0);
        $avgPerLiter = $litersTotal > 0 ? $priceTotal / $litersTotal : 0;

        $lastMovement = Movement::query()
            ->orderByRaw('COALESCE(date, created_at) DESC')
            ->first();

        return [
            Stat::make('Movimenti totali', number_format($total, 0, ',', '.'))
                ->icon('heroicon-o-clipboard-document-check'),
            Stat::make('Litri totali', number_format($litersTotal, 2, ',', '.') . ' L')
                ->icon('heroicon-o-beaker'),
            Stat::make('Costo totale', number_format($priceTotal, 2, ',', '.') . ' €')
                ->icon('heroicon-o-currency-euro'),
            Stat::make('Prezzo medio/L', number_format($avgPerLiter, 3, ',', '.') . ' €/L')
                ->icon('heroicon-o-scale'),
            Stat::make('AdBlue totale', number_format($adblueTotal, 2, ',', '.') . ' L')
                ->icon('heroicon-o-adjustments-horizontal'),
            Stat::make('Ultimo movimento', $lastMovement?->date?->format('d/m/Y H:i') ?? $lastMovement?->created_at?->format('d/m/Y H:i') ?? 'N/D')
                ->icon('heroicon-o-clock'),
        ];
    }
}
