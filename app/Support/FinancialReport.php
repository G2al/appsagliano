<?php

namespace App\Support;

use App\Models\Maintenance;
use App\Models\Movement;
use App\Models\TollRoadExpense;
use App\Models\UserSalary;
use App\Models\Vehicle;
use App\Models\VehicleRevenue;
use Illuminate\Database\Eloquent\Builder;

class FinancialReport
{
    public static function summary(FinancialReportPeriod $period): array
    {
        $revenuesQuery = VehicleRevenue::query();
        $period->applyToBuilder($revenuesQuery, 'date');
        $revenues = $revenuesQuery
            ->selectRaw('
                COUNT(*) as revenue_records_count,
                COALESCE(SUM(amount_ex_vat), 0) as revenues_ex_vat_total,
                COALESCE(SUM(amount_inc_vat), 0) as revenues_inc_vat_total
            ')
            ->first();

        $refuelsQuery = Movement::query();
        $period->applyToBuilder($refuelsQuery, 'date');
        $refuels = $refuelsQuery
            ->selectRaw('
                COUNT(*) as refuels_count,
                COALESCE(SUM(price), 0) as refuels_total
            ')
            ->first();

        $maintenancesQuery = Maintenance::query();
        $period->applyToBuilder($maintenancesQuery, 'date');
        $maintenances = $maintenancesQuery
            ->selectRaw('
                COUNT(*) as maintenances_count,
                COALESCE(SUM(price), 0) as maintenances_total
            ')
            ->first();

        $salariesQuery = UserSalary::query();
        $period->applyToBuilder($salariesQuery, 'date');
        $salaries = $salariesQuery
            ->selectRaw('
                COUNT(*) as salary_records_count,
                COALESCE(SUM(amount), 0) as salaries_total
            ')
            ->first();

        $tollsQuery = TollRoadExpense::query();
        $period->applyToBuilder($tollsQuery, 'date');
        $tolls = $tollsQuery
            ->selectRaw('
                COUNT(*) as toll_records_count,
                COALESCE(SUM(amount), 0) as tolls_total
            ')
            ->first();

        $revenuesExVatTotal = (float) ($revenues?->revenues_ex_vat_total ?? 0);
        $revenuesIncVatTotal = (float) ($revenues?->revenues_inc_vat_total ?? 0);
        $refuelsTotal = (float) ($refuels?->refuels_total ?? 0);
        $maintenancesTotal = (float) ($maintenances?->maintenances_total ?? 0);
        $salariesTotal = (float) ($salaries?->salaries_total ?? 0);
        $tollsTotal = (float) ($tolls?->tolls_total ?? 0);

        $vehicleOperatingCosts = $refuelsTotal + $maintenancesTotal;
        $totalCosts = $vehicleOperatingCosts + $salariesTotal + $tollsTotal;

        return [
            'revenues_ex_vat_total' => $revenuesExVatTotal,
            'revenues_inc_vat_total' => $revenuesIncVatTotal,
            'refuels_total' => $refuelsTotal,
            'maintenances_total' => $maintenancesTotal,
            'salaries_total' => $salariesTotal,
            'tolls_total' => $tollsTotal,
            'vehicle_operating_costs_total' => $vehicleOperatingCosts,
            'total_costs' => $totalCosts,
            'vehicle_margin_total' => $revenuesExVatTotal - $vehicleOperatingCosts,
            'net_margin_total' => $revenuesExVatTotal - $totalCosts,
            'revenue_records_count' => (int) ($revenues?->revenue_records_count ?? 0),
            'refuels_count' => (int) ($refuels?->refuels_count ?? 0),
            'maintenances_count' => (int) ($maintenances?->maintenances_count ?? 0),
            'salary_records_count' => (int) ($salaries?->salary_records_count ?? 0),
            'toll_records_count' => (int) ($tolls?->toll_records_count ?? 0),
        ];
    }

    public static function vehiclePerformanceQuery(FinancialReportPeriod $period): Builder
    {
        $revenueStatsQuery = VehicleRevenue::query()
            ->selectRaw('
                vehicle_id,
                COUNT(*) as revenue_records_count,
                COALESCE(SUM(amount_ex_vat), 0) as revenues_ex_vat_total,
                COALESCE(SUM(amount_inc_vat), 0) as revenues_inc_vat_total
            ')
            ->groupBy('vehicle_id');
        $period->applyToBuilder($revenueStatsQuery, 'date');

        $refuelStatsQuery = Movement::query()
            ->whereNotNull('vehicle_id')
            ->selectRaw('
                vehicle_id,
                COUNT(*) as refuels_count,
                COALESCE(SUM(price), 0) as refuels_total
            ')
            ->groupBy('vehicle_id');
        $period->applyToBuilder($refuelStatsQuery, 'date');

        $maintenanceStatsQuery = Maintenance::query()
            ->selectRaw('
                vehicle_id,
                COUNT(*) as maintenances_count,
                COALESCE(SUM(price), 0) as maintenances_total
            ')
            ->groupBy('vehicle_id');
        $period->applyToBuilder($maintenanceStatsQuery, 'date');

        return Vehicle::query()
            ->select('vehicles.*')
            ->leftJoinSub($revenueStatsQuery, 'revenue_stats', function ($join): void {
                $join->on('revenue_stats.vehicle_id', '=', 'vehicles.id');
            })
            ->leftJoinSub($refuelStatsQuery, 'refuel_stats', function ($join): void {
                $join->on('refuel_stats.vehicle_id', '=', 'vehicles.id');
            })
            ->leftJoinSub($maintenanceStatsQuery, 'maintenance_stats', function ($join): void {
                $join->on('maintenance_stats.vehicle_id', '=', 'vehicles.id');
            })
            ->addSelect([
                'revenue_records_count' => 'revenue_stats.revenue_records_count',
                'revenues_ex_vat_total' => 'revenue_stats.revenues_ex_vat_total',
                'revenues_inc_vat_total' => 'revenue_stats.revenues_inc_vat_total',
                'refuels_count' => 'refuel_stats.refuels_count',
                'refuels_total' => 'refuel_stats.refuels_total',
                'maintenances_count' => 'maintenance_stats.maintenances_count',
                'maintenances_total' => 'maintenance_stats.maintenances_total',
            ])
            ->selectRaw('
                (
                    COALESCE(refuel_stats.refuels_total, 0) +
                    COALESCE(maintenance_stats.maintenances_total, 0)
                ) as vehicle_total_costs
            ')
            ->selectRaw('
                (
                    COALESCE(revenue_stats.revenues_ex_vat_total, 0) -
                    COALESCE(refuel_stats.refuels_total, 0) -
                    COALESCE(maintenance_stats.maintenances_total, 0)
                ) as operating_margin
            ')
            ->where(function (Builder $query): void {
                $query
                    ->whereNotNull('revenue_stats.vehicle_id')
                    ->orWhereNotNull('refuel_stats.vehicle_id')
                    ->orWhereNotNull('maintenance_stats.vehicle_id');
            });
    }
}
