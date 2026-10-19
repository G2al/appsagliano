<?php

namespace Database\Seeders;

use App\Models\Filiale;
use Illuminate\Database\Seeder;

class VialoFilialeSeeder extends Seeder
{
    /**
     * Seed Vialo demo filiali (dati fittizi, usate dal bordero).
     */
    public function run(): void
    {
        $filiali = [
            ['name' => 'Filiale Nord', 'codice' => 'FN01'],
            ['name' => 'Filiale Sud', 'codice' => 'FS01'],
            ['name' => 'Filiale Est', 'codice' => 'FE01'],
            ['name' => 'Filiale Ovest', 'codice' => 'FO01'],
            ['name' => 'Filiale Centro', 'codice' => 'FC01'],
            ['name' => 'Filiale Adriatica', 'codice' => 'FA01'],
            ['name' => 'Filiale Logistica Interna', 'codice' => 'FL01'],
        ];

        foreach ($filiali as $filiale) {
            Filiale::query()->updateOrCreate(
                ['codice' => $filiale['codice']],
                $filiale
            );
        }

        $this->command?->info('Filiali demo Vialo create: ' . count($filiali) . '.');
    }
}
