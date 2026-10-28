<?php

namespace Database\Seeders;

use App\Models\SupplierType;
use Illuminate\Database\Seeder;

class RedifSupplierTypeSeeder extends Seeder
{
    /**
     * Tipologie fornitori di Redif, ricavate dai fornitori reali forniti
     * dal cliente. Idempotente.
     */
    public function run(): void
    {
        $types = [
            'Gommista',
            'Meccanico',
            'Frigorista',
            'Ricambi per camion',
            'Sponda',
            'Carrelli',
            'Lavaggio',
            'Elettrauto',
        ];

        foreach ($types as $type) {
            SupplierType::query()->updateOrCreate(['name' => $type]);
        }

        $this->command?->info('Tipologie fornitori Redif create: ' . count($types) . '.');
    }
}
