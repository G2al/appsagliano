<?php

namespace Database\Seeders;

use App\Models\Station;
use Illuminate\Database\Seeder;

class RedifStationSeeder extends Seeder
{
    /**
     * Importa le stazioni di servizio reali di Redif. Stazioni standard
     * (nessun buono), saldo credito a 0: lo imposta il cliente. Idempotente.
     */
    public function run(): void
    {
        $stations = [
            ['name' => 'Gold Fuel', 'address' => 'San Marcellino'],
            ['name' => 'Faboil', 'address' => 'Casaluce'],
            ['name' => 'Star Fuel', 'address' => 'Maddaloni'],
        ];

        Station::withoutEvents(function () use ($stations): void {
            foreach ($stations as $station) {
                Station::query()->updateOrCreate(
                    ['name' => $station['name']],
                    [
                        'address' => $station['address'],
                        'credit_balance' => 0,
                        'uses_vouchers' => false,
                    ]
                );
            }
        });

        $this->command?->info('Stazioni Redif importate: ' . count($stations) . '.');
    }
}
