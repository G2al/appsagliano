<?php

namespace App\Filament\Resources\VoucherRefuelReportResource\Widgets;

use App\Filament\Resources\VoucherRefuelReportResource\Pages\ListVoucherRefuelReports;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VoucherRefuelStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected function getColumns(): int
    {
        return 2;
    }

    protected function getTablePage(): string
    {
        return ListVoucherRefuelReports::class;
    }

    protected function getStats(): array
    {
        $query = $this->getPageTableQuery();

        $total = (int) (clone $query)->count();
        $priceTotal = (float) (clone $query)->sum('price');
        $litersTotal = (float) (clone $query)->sum('liters');
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
