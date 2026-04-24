<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoBaseSeeder extends Seeder
{
    /**
     * Seed the demo base data required to use the app.
     */
    public function run(): void
    {
        $this->call([
            VehicleSeeder::class,
            StationSeeder::class,
            SupplierSeeder::class,
            DocumentFolderTemplateSeeder::class,
        ]);
    }
}
