<?php

namespace App\Filament\Resources\VoucherRefuelReportResource\Widgets;

use App\Models\Movement;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VoucherRefuelStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totals = Movement::query()
            ->where('is_voucher', true)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(price), 0) as price_total')
            ->selectRaw('COALESCE(SUM(liters), 0) as liters_total')
            ->first();

        $total = (int) ($totals?->total ?? 0);
        $priceTotal = (float) ($totals?->price_total ?? 0);
        $litersTotal = (float) ($totals?->liters_total ?? 0);
        $avgPrice = $total > 0 ? $priceTotal / $total : 0;

        return [
            Stat::make('Totale buoni', number_format($total, 0, ',', '.'))
                ->icon('heroicon-o-ticket'),
            Stat::make('Spesa totale buoni', 'EUR ' . number_format($priceTotal, 2, ',', '.'))
                ->icon('heroicon-o-currency-euro'),
            Stat::make('Litri totali', number_format($litersTotal, 2, ',', '.') . ' L')
                ->icon('heroicon-o-beaker'),
            Stat::make('Media prezzo per buono', 'EUR ' . number_format($avgPrice, 2, ',', '.'))
                ->icon('heroicon-o-scale'),
        ];
    }
}

