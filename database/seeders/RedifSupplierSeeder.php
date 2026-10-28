<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\SupplierType;
use Illuminate\Database\Seeder;

class RedifSupplierSeeder extends Seeder
{
    /**
     * Importa i fornitori reali di Redif con la relativa tipologia.
     * Nessun telefono/email (dato mancante, lo aggiunge il cliente).
     * Idempotente, richiede RedifSupplierTypeSeeder gia' eseguito.
     */
    public function run(): void
    {
        $suppliers = [
            'SATEP' => 'Gommista',
            'FISCONE' => 'Meccanico',
            'FREDDOITALIA' => 'Frigorista',
            'TECNORICAMBI' => 'Ricambi per camion',
            'F.LLI BORRELLI' => 'Sponda',
            '4.0' => 'Gommista',
            'PG CAR' => 'Carrelli',
            'COSCIONE' => 'Carrelli',
            'CAMPANILE' => 'Lavaggio',
            'DITTA GIUSEPPE CASSANDRA' => 'Elettrauto',
            'MEDWASH' => 'Lavaggio',
            'M.S.T AUTORICAMBI' => 'Ricambi per camion',
            'RIC.OFF' => 'Ricambi per camion',
        ];

        $imported = 0;

        foreach ($suppliers as $name => $typeName) {
            $type = SupplierType::query()->where('name', $typeName)->first();

            Supplier::query()->updateOrCreate(
                ['name' => $name],
                ['supplier_type_id' => $type?->id]
            );

            $imported++;
        }

        $this->command?->info("Importati {$imported} fornitori Redif.");
    }
}
