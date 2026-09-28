<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Seed Mega Trasporti base maintenance suppliers.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'Officina Mega Truck',
                'phone' => '+390171100001',
                'email' => 'accettazione@officinamegatruck.it',
                'address' => 'Via degli Artigiani 14, Milano (MI)',
            ],
            [
                'name' => 'Ricambi Diesel Service',
                'phone' => '+390172100002',
                'email' => 'info@ricambidieselservice.it',
                'address' => 'Via Industriale 7, Bergamo (BG)',
            ],
            [
                'name' => 'Pneus Logistic Center',
                'phone' => '+390173100003',
                'email' => 'commerciale@pneuslogistic.it',
                'address' => 'Corso Milano 55, Brescia (BS)',
            ],
            [
                'name' => 'Truck Elettrauto Lombardia',
                'phone' => '+390111000004',
                'email' => 'officina@truckelettrauto.it',
                'address' => 'Via Cristoforo Colombo 128, Milano (MI)',
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::query()->updateOrCreate(
                ['name' => $supplier['name']],
                $supplier
            );
        }
    }
}
