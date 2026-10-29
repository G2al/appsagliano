<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class VialoPlatformSeeder extends Seeder
{
    /**
     * Seed Vialo demo platforms (anagrafica fittizia).
     *
     * Due gruppi distinti che condividono lo stesso modello Platform:
     * - piattaforme "rifornimenti" (indirizzi per i movimenti carburante);
     * - piattaforme "bordero" (MD, Conad, Megamark...), con le diciture
     *   (secco, freschi, frutta...) usate per la cascata del bordero.
     */
    public function run(): void
    {
        $refuelPlatforms = [
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

        foreach ($refuelPlatforms as $name => $addresses) {
            $platform = Platform::query()->updateOrCreate(['name' => $name]);

            foreach ($addresses as $address) {
                $platform->addresses()->firstOrCreate(['address' => $address]);
            }
        }

        $borderoPlatforms = [
            'MD' => ['Secco', 'Freschi', 'Frutta', 'Surgelati'],
            'Conad' => ['Secco', 'Freschi', 'Frutta'],
            'Megamark' => ['Secco', 'Freschi', 'Surgelati'],
        ];

        foreach ($borderoPlatforms as $name => $goodsTypes) {
            $platform = Platform::query()->updateOrCreate(['name' => $name]);

            foreach ($goodsTypes as $goodsType) {
                $platform->goodsTypes()->firstOrCreate(['name' => $goodsType]);
            }
        }

        $this->command?->info('Piattaforme demo Vialo create: ' . (count($refuelPlatforms) + count($borderoPlatforms)) . '.');
    }
}
