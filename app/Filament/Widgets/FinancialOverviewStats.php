<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithFinancialReportData;
use App\Support\FinancialReport;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinancialOverviewStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    use InteractsWithFinancialReportData;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $summary = FinancialReport::summary($this->resolveFinancialReportPeriod());

        return [
            Stat::make('Entrate veicoli nette', $this->formatMoney($summary['revenues_ex_vat_total']))
                ->description(number_format($summary['revenue_records_count'], 0, ',', '.') . ' registrazioni')
                ->icon('heroicon-o-banknotes'),
            Stat::make('Entrate veicoli con IVA', $this->formatMoney($summary['revenues_inc_vat_total']))
                ->description('Calcolo storico con IVA salvata')
                ->icon('heroicon-o-calculator'),
            Stat::make('Costi rifornimenti', $this->formatMoney($summary['refuels_total']))
                ->description(number_format($summary['refuels_count'], 0, ',', '.') . ' movimenti')
                ->icon('heroicon-o-truck'),
            Stat::make('Costi manutenzioni', $this->formatMoney($summary['maintenances_total']))
                ->description(number_format($summary['maintenances_count'], 0, ',', '.') . ' interventi')
                ->icon('heroicon-o-wrench-screwdriver'),
            Stat::make('Costi stipendi', $this->formatMoney($summary['salaries_total']))
                ->description(number_format($summary['salary_records_count'], 0, ',', '.') . ' registrazioni')
                ->icon('heroicon-o-users'),
            Stat::make('Costi autostrade', $this->formatMoney($summary['tolls_total']))
                ->description(number_format($summary['toll_records_count'], 0, ',', '.') . ' registrazioni')
                ->icon('heroicon-o-map'),
            Stat::make('Margine veicoli', $this->formatMoney($summary['vehicle_margin_total']))
                ->description('Entrate nette - rifornimenti - manutenzioni')
                ->icon('heroicon-o-chart-bar'),
            Stat::make('Utile finale', $this->formatMoney($summary['net_margin_total']))
                ->description('Margine veicoli - stipendi - autostrade')
                ->color($summary['net_margin_total'] >= 0 ? 'success' : 'danger')
                ->icon('heroicon-o-presentation-chart-line'),
        ];
    }
}
