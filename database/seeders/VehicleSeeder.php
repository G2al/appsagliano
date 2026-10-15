<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    /**
     * Seed Vialo demo vehicles (flotta fittizia, nessun dato reale).
     */
    public function run(): void
    {
        $models = [
            ['Volvo FH 500', 'Bianco'],
            ['Scania R 450', 'Blu'],
            ['Mercedes Actros 1845', 'Grigio'],
            ['DAF XF 480', 'Nero'],
            ['Iveco S-Way 460', 'Bianco'],
            ['MAN TGX 18.510', 'Rosso'],
            ['Renault T High 520', 'Blu'],
            ['Volvo FH16 750', 'Bianco'],
            ['Scania S 500', 'Grigio'],
            ['Mercedes Arocs 2545', 'Giallo'],
            ['DAF CF 450', 'Bianco'],
            ['Iveco Stralis 460', 'Blu'],
            ['MAN TGS 18.440', 'Nero'],
            ['Renault T 480', 'Bianco'],
            ['Volvo FM 420', 'Grigio'],
            ['Scania G 410', 'Blu'],
            ['Mercedes Actros 2545', 'Bianco'],
            ['DAF XG 530', 'Nero'],
            ['Iveco X-Way 420', 'Bianco'],
            ['MAN TGX 26.510', 'Rosso'],
        ];

        $vehicles = [];

        foreach ($models as $index => [$name, $color]) {
            $plateNumber = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);

            $vehicles[] = [
                'plate' => "VL{$plateNumber}AA",
                'name' => $name,
                'color' => $color,
                'current_km' => random_int(40000, 230000),
                'maintenance_km' => random_int(240000, 260000),
            ];
        }

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
