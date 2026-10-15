<?php

namespace Database\Seeders;

use App\Models\Station;
use Illuminate\Database\Seeder;

class StationSeeder extends Seeder
{
    /**
     * Seed Vialo demo fuel stations (dati fittizi).
     */
    public function run(): void
    {
        $stations = [
            [
                'name' => 'Q8 Autostrada Nord',
                'address' => 'Via Nazionale 114, Milano (MI)',
                'credit_balance' => 12500.00,
                'uses_vouchers' => false,
            ],
            [
                'name' => 'Eni Tangenziale Est',
                'address' => 'Via Emilia 88, Bologna (BO)',
                'credit_balance' => 8200.00,
                'uses_vouchers' => true,
            ],
            [
                'name' => 'Tamoil Interporto',
                'address' => 'Via Interporto 12, Verona (VR)',
                'credit_balance' => 6400.00,
                'uses_vouchers' => false,
            ],
            [
                'name' => 'IP Casello Sud',
                'address' => 'Corso Europa 37, Padova (PD)',
                'credit_balance' => 9100.00,
                'uses_vouchers' => true,
            ],
            [
                'name' => 'Esso Zona Industriale',
                'address' => 'Via dell\'Artigianato 22, Bergamo (BG)',
                'credit_balance' => 7300.00,
                'uses_vouchers' => false,
            ],
            [
                'name' => 'Agip Porta Sud',
                'address' => 'Viale della Repubblica 9, Brescia (BS)',
                'credit_balance' => 5400.00,
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
