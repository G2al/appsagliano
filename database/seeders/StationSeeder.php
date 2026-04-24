<?php

namespace Database\Seeders;

use App\Models\Station;
use Illuminate\Database\Seeder;

class StationSeeder extends Seeder
{
    /**
     * Seed base demo fuel stations.
     */
    public function run(): void
    {
        $stations = [
            [
                'name' => 'Q8 Orbassano',
                'address' => 'Strada Torino 114, Orbassano (TO)',
                'credit_balance' => 12500.00,
                'uses_vouchers' => false,
            ],
            [
                'name' => 'Eni Fossano',
                'address' => 'Via Torino 88, Fossano (CN)',
                'credit_balance' => 8200.00,
                'uses_vouchers' => true,
            ],
            [
                'name' => 'Tamoil Cuneo Est',
                'address' => 'Via Castelletto Stura 12, Cuneo (CN)',
                'credit_balance' => 6400.00,
                'uses_vouchers' => false,
            ],
            [
                'name' => 'IP Alba',
                'address' => 'Corso Canale 37, Alba (CN)',
                'credit_balance' => 9100.00,
                'uses_vouchers' => true,
            ],
        ];

        Station::withoutEvents(function () use ($stations): void {
            foreach ($stations as $station) {
                Station::query()->updateOrCreate(
                    ['name' => $station['name']],
                    $station
                );
            }
        });
    }
}
