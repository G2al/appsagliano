<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class VialoPlatformSeeder extends Seeder
{
    /**
     * Seed Vialo demo platforms (anagrafica fittizia). Una piattaforma ha
     * piu' indirizzi apposta, per mostrare in demo la selezione obbligatoria
     * lato viaggio quando sono presenti piu' sedi.
     */
    public function run(): void
    {
        $platforms = [
            'Deposito Nord' => ['Via Industriale 1, Milano (MI)'],
            'Hub Logistico Sud' => ['Via Appia 450, Roma (RM)'],
            'Centro Smistamento Est' => ['Via Emilia 200, Bologna (BO)'],
            'Piattaforma Ovest' => ['Corso Francia 88, Torino (TO)'],
            'Magazzino Centrale' => [
                'Via dei Trasporti 10, Firenze (FI)',
                'Via dei Trasporti 25, Firenze (FI)',
            ],
            'Terminal Adriatico' => ['Via del Porto 5, Ancona (AN)'],
            'Snodo Logistico Interno' => ['Via Interporto 30, Padova (PD)'],
        ];

        foreach ($platforms as $name => $addresses) {
            $platform = Platform::query()->updateOrCreate(['name' => $name]);

            foreach ($addresses as $address) {
                $platform->addresses()->firstOrCreate(['address' => $address]);
            }
        }

        $this->command?->info('Piattaforme demo Vialo create: ' . count($platforms) . '.');
    }
}
