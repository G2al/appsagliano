<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class RedifVehicleSeeder extends Seeder
{
    /**
     * Parco mezzi reale di Redif (tutte "motrice"). Km attuali/manutenzione
     * a 0: dato reale, lo aggiorna il cliente. Idempotente (chiave: targa).
     */
    private const PLATES = [
        'CN 057 TL', 'DJ 349 EL', 'DM 742 GM', 'DN 869 NM', 'DP 196 RY',
        'DW 047 RF', 'DY 193 LZ', 'EG 973 VJ', 'EW935TL', 'FW 492 KN',
        'GC 827 SV', 'GF 131 LM', 'GJ 061 NK', 'GK 241 XC', 'GM 714 GR',
        'GN 870 AZ', 'GN 871 AZ', 'HA 804 RK', 'HA 849 RK',
    ];

    public function run(): void
    {
        $imported = 0;

        Vehicle::withoutEvents(function () use (&$imported): void {
            foreach (self::PLATES as $rawPlate) {
                $plate = strtoupper(str_replace(' ', '', $rawPlate));

                Vehicle::query()->updateOrCreate(
                    ['plate' => $plate],
                    [
                        'name' => 'Motrice',
                        'current_km' => 0,
                        'maintenance_km' => 0,
                    ]
                );

                $imported++;
            }
        });

        $this->command?->info("Importati {$imported} veicoli Redif.");
    }
}
