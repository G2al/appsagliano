<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Seed Vialo demo suppliers (dati fittizi, nessuna ragione sociale reale).
     */
    public function run(): void
    {
        $suppliers = [
            ['name' => 'Officina Nord Truck', 'city' => 'Milano (MI)'],
            ['name' => 'Ricambi Diesel Service', 'city' => 'Bergamo (BG)'],
            ['name' => 'Pneus Logistic Center', 'city' => 'Brescia (BS)'],
            ['name' => 'Truck Elettrauto Lombardia', 'city' => 'Milano (MI)'],
            ['name' => 'Carrozzeria Centro Nord', 'city' => 'Verona (VR)'],
            ['name' => 'Autofficina Veloce', 'city' => 'Padova (PD)'],
            ['name' => 'Ricambi Express', 'city' => 'Bologna (BO)'],
            ['name' => 'Gommista Autostrada', 'city' => 'Modena (MO)'],
            ['name' => 'Service Truck Center', 'city' => 'Parma (PR)'],
            ['name' => 'Officina Diesel Plus', 'city' => 'Reggio Emilia (RE)'],
            ['name' => 'Autoricambi Nord Est', 'city' => 'Treviso (TV)'],
            ['name' => 'Centro Revisioni Rapide', 'city' => 'Vicenza (VI)'],
            ['name' => 'Elettrauto Truck Service', 'city' => 'Mantova (MN)'],
        ];

        foreach ($suppliers as $index => $supplier) {
            $slug = strtolower(preg_replace('/[^a-z0-9]/i', '', $supplier['name']));
            $phoneSuffix = str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT);

            Supplier::query()->updateOrCreate(
                ['name' => $supplier['name']],
                [
                    'phone' => "+3902{$phoneSuffix}",
                    'email' => "info@{$slug}.appvialo.it",
                    'address' => "Via dell'Artigianato " . ($index + 1) . ', ' . $supplier['city'],
                ]
            );
        }
    }
}
