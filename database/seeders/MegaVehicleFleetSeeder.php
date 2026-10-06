<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class MegaVehicleFleetSeeder extends Seeder
{
    /**
     * Importa il parco mezzi reale di Mega Trasporti da
     * database/seeders/data/mega_vehicles.csv (colonne: Targa;Modello).
     *
     * Idempotente: rilanciabile senza creare doppioni, aggiorna il veicolo
     * esistente in base alla targa.
     */
    public function run(): void
    {
        $colors = ['Bianco', 'Grigio', 'Blu', 'Rosso', 'Nero', 'Argento', 'Verde'];

        $path = __DIR__ . '/data/mega_vehicles.csv';

        $handle = fopen($path, 'r');
        fgetcsv($handle, separator: ';'); // intestazione

        $imported = 0;

        while (($row = fgetcsv($handle, separator: ';')) !== false) {
            [$plate, $model] = $row;

            Vehicle::updateOrCreate(
                ['plate' => trim($plate)],
                [
                    'name' => trim($model),
                    'color' => $colors[array_rand($colors)],
                    'current_km' => 0,
                    'maintenance_km' => 0,
                ]
            );

            $imported++;
        }

        fclose($handle);

        $this->command?->info("Importati {$imported} veicoli dal parco mezzi Mega Trasporti.");
    }
}
