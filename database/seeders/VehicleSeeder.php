<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    /**
     * Seed base demo vehicles.
     */
    public function run(): void
    {
        $vehicles = [
            [
                'plate' => 'TT001AA',
                'name' => 'Volvo FH 500',
                'color' => 'Bianco',
                'current_km' => 186450,
                'maintenance_km' => 195000,
            ],
            [
                'plate' => 'TT002BB',
                'name' => 'Scania R 450',
                'color' => 'Blu',
                'current_km' => 142380,
                'maintenance_km' => 150000,
            ],
            [
                'plate' => 'TT003CC',
                'name' => 'Mercedes Actros 1845',
                'color' => 'Grigio',
                'current_km' => 214900,
                'maintenance_km' => 225000,
            ],
            [
                'plate' => 'TT004DD',
                'name' => 'DAF XF 480',
                'color' => 'Nero',
                'current_km' => 97820,
                'maintenance_km' => 105000,
            ],
            [
                'plate' => 'TT005EE',
                'name' => 'Iveco S-Way 460',
                'color' => 'Bianco',
                'current_km' => 121560,
                'maintenance_km' => 130000,
            ],
        ];

        Vehicle::withoutEvents(function () use ($vehicles): void {
            foreach ($vehicles as $vehicle) {
                Vehicle::query()->updateOrCreate(
                    ['plate' => $vehicle['plate']],
                    $vehicle
                );
            }
        });
    }
}
