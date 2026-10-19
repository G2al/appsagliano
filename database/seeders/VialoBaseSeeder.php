<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class VialoBaseSeeder extends Seeder
{
    /**
     * Seed the Vialo demo base data required to use the app.
     */
    public function run(): void
    {
        $this->call([
            VehicleSeeder::class,
            StationSeeder::class,
            SupplierSeeder::class,
            VialoPlatformSeeder::class,
            VialoFilialeSeeder::class,
            DocumentFolderTemplateSeeder::class,
        ]);
    }
}
