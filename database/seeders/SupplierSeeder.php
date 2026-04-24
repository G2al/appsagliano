<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Seed base demo maintenance suppliers.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'Officina Nord Truck',
                'phone' => '+390171100001',
                'email' => 'accettazione@officinanordtruck.demo',
                'address' => 'Via degli Artigiani 14, Cuneo (CN)',
            ],
            [
                'name' => 'Ricambi Diesel Service',
                'phone' => '+390172100002',
                'email' => 'info@ricambidieselservice.demo',
                'address' => 'Via Industriale 7, Savigliano (CN)',
            ],
            [
                'name' => 'Pneus Logistic Center',
                'phone' => '+390173100003',
                'email' => 'commerciale@pneuslogistic.demo',
                'address' => 'Corso Asti 55, Alba (CN)',
            ],
            [
                'name' => 'Truck Elettrauto Piemonte',
                'phone' => '+390111000004',
                'email' => 'officina@truckelettrauto.demo',
                'address' => 'Strada del Drosso 128, Torino (TO)',
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
