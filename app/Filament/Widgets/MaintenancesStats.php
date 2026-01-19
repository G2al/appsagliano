<?php

namespace App\Filament\Widgets;

use App\Models\Maintenance;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MaintenancesStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    protected function getStats(): array
    {
        [$start, $end, $vehicleId, $supplierId] = $this->resolveFilters();

        $query = Maintenance::query()
            ->whereBetween('date', [$start, $end]);

        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $totals = $query
            ->selectRaw('
                COUNT(*) as maintenances_count,
                COALESCE(SUM(price), 0) as price_total,
                COUNT(DISTINCT supplier_id) as suppliers_count
            ')
            ->first();

        $priceTotal = (float) ($totals?->price_total ?? 0);
        $count = (int) ($totals?->maintenances_count ?? 0);
        $suppliers = (int) ($totals?->suppliers_count ?? 0);

        return [
            Stat::make('Totale speso', '€ ' . number_format($priceTotal, 2, ',', '.'))
                ->icon('heroicon-o-currency-euro'),
            Stat::make('Interventi', number_format($count, 0, ',', '.'))
                ->icon('heroicon-o-wrench-screwdriver'),
            Stat::make('Fornitori', number_format($suppliers, 0, ',', '.'))
                ->icon('heroicon-o-building-storefront'),
        ];
    }

    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon, 2: ?int, 3: ?int}
     */
    private function resolveFilters(): array
    {
        $filters = $this->filters ?? [];
        $startRaw = $filters['start_date'] ?? null;
        $endRaw = $filters['end_date'] ?? null;

        $start = Carbon::parse($startRaw ?: now()->startOfMonth())->startOfDay();
        $end = Carbon::parse($endRaw ?: now())->endOfDay();

        return [
            $start,
            $end,
            null,
            null,
        ];
    }
}
