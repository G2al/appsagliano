<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class MegaPlatformSeeder extends Seeder
{
    /**
     * Importa le piattaforme reali di Mega Trasporti da
     * database/seeders/data/mega_platforms.txt (un nome per riga, ricavati
     * dalla colonna "CANTIERE" del file di esempio gasolio del titolare).
     *
     * Nessun collegamento ai veicoli: popola solo l'anagrafica piattaforme.
     * Idempotente: rilanciabile senza creare doppioni.
     */
    public function run(): void
    {
        $path = __DIR__ . '/data/mega_platforms.txt';

        $names = array_filter(array_map('trim', file($path)), fn ($line) => $line !== '');

        $imported = 0;

        foreach ($names as $name) {
            Platform::updateOrCreate(['name' => $name]);
            $imported++;
        }

        $this->command?->info("Importate {$imported} piattaforme Mega Trasporti.");
    }
}
