<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movement;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plate' => ['required', 'string', 'max:255', Rule::unique('vehicles', 'plate')],
            'color' => ['nullable', 'string', 'max:255'],
        ], [
            'required' => 'Il campo :attribute e obbligatorio.',
            'plate.unique' => 'Esiste gia un veicolo con questa targa.',
        ], [
            'name' => 'nome/categoria',
            'plate' => 'targa',
            'color' => 'colore',
        ]);

        $vehicle = Vehicle::create([
            ...$validated,
            'current_km' => 0,
            'maintenance_km' => 0,
        ]);

        return response()->json([
            'id' => $vehicle->id,
            'name' => $vehicle->name,
            'plate' => $vehicle->plate,
            'color' => $vehicle->color,
            'current_km' => $vehicle->current_km,
            'maintenance_km' => $vehicle->maintenance_km,
            'refuel_km_per_liter_avg' => null,
        ], 201);
    }
}
