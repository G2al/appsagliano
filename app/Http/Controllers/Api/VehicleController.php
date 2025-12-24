<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movement;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;

class VehicleController extends Controller
{
    public function index(): JsonResponse
    {
        $vehicles = Vehicle::select('id', 'name', 'plate', 'color', 'current_km', 'maintenance_km')
            ->orderBy('name')
            ->get();

        $statsByVehicleId = Movement::query()
            ->whereNotNull('vehicle_id')
            ->selectRaw('
                vehicle_id,
                SUM(
                    CASE
                        WHEN km_end IS NOT NULL
                            AND km_start IS NOT NULL
                            AND km_end >= km_start
                            AND liters IS NOT NULL
                            AND liters > 0
                        THEN km_end - km_start
                        ELSE 0
                    END
                ) as km_total,
                SUM(
                    CASE
                        WHEN km_end IS NOT NULL
                            AND km_start IS NOT NULL
                            AND km_end >= km_start
                            AND liters IS NOT NULL
                            AND liters > 0
                        THEN liters
                        ELSE 0
                    END
                ) as liters_total
            ')
            ->groupBy('vehicle_id')
            ->get()
            ->keyBy('vehicle_id');

        return response()->json(
            $vehicles->map(function (Vehicle $vehicle) use ($statsByVehicleId) {
                $stats = $statsByVehicleId->get($vehicle->id);
                $kmTotal = (float) ($stats?->km_total ?? 0);
                $litersTotal = (float) ($stats?->liters_total ?? 0);
                $avgKmPerLiter = $litersTotal > 0 ? round($kmTotal / $litersTotal, 2) : null;

                return [
                    'id' => $vehicle->id,
                    'name' => $vehicle->name,
                    'plate' => $vehicle->plate,
                    'color' => $vehicle->color,
                    'current_km' => $vehicle->current_km,
                    'maintenance_km' => $vehicle->maintenance_km,
                    'refuel_km_per_liter_avg' => $avgKmPerLiter,
                ];
            })->values()
        );
    }
}
