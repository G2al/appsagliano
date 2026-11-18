<?php

namespace Database\Seeders;

use App\Models\GoodsType;
use App\Models\Platform;
use App\Models\PriceRule;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VialoFilialeSeeder extends Seeder
{
    /**
     * Seed Vialo demo filiali (dati fittizi, usate dal bordero), ciascuna
     * agganciata a una piattaforma e una dicitura, con un listino prezzi
     * per numero di viaggio nella giornata (1 - 6).
     */
    public function run(): void
    {
        $filialiByPlatform = [
            'MD' => [
                'Secco' => [['name' => 'Filiale Nord', 'codice' => 'MD-SE-01'], ['name' => 'Filiale Sud', 'codice' => 'MD-SE-02']],
                'Freschi' => [['name' => 'Filiale Nord', 'codice' => 'MD-FR-01'], ['name' => 'Filiale Centro', 'codice' => 'MD-FR-02']],
                'Frutta' => [['name' => 'Filiale Est', 'codice' => 'MD-FT-01']],
                'Surgelati' => [['name' => 'Filiale Ovest', 'codice' => 'MD-SU-01']],
            ],
            'Conad' => [
                'Secco' => [['name' => 'Filiale Adriatica', 'codice' => 'CO-SE-01']],
                'Freschi' => [['name' => 'Filiale Logistica Interna', 'codice' => 'CO-FR-01']],
                'Frutta' => [['name' => 'Filiale Centro', 'codice' => 'CO-FT-01']],
            ],
            'Megamark' => [
                'Secco' => [['name' => 'Filiale Sud', 'codice' => 'MG-SE-01']],
                'Freschi' => [['name' => 'Filiale Est', 'codice' => 'MG-FR-01']],
                'Surgelati' => [['name' => 'Filiale Nord', 'codice' => 'MG-SU-01']],
            ],
        ];

        $filialiCreated = 0;

        foreach ($filialiByPlatform as $platformName => $goodsTypes) {
            $platform = Platform::query()->where('name', $platformName)->first();

            if (! $platform) {
                continue;
            }

            foreach ($goodsTypes as $goodsTypeName => $filiali) {
                $goodsType = GoodsType::query()
                    ->where('platform_id', $platform->id)
                    ->where('name', $goodsTypeName)
                    ->first();

                if (! $goodsType) {
                    continue;
                }

                foreach ($filiali as $filialeData) {
                    $filiale = $goodsType->filiali()->updateOrCreate(
                        ['codice' => $filialeData['codice']],
                        ['name' => $filialeData['name'], 'platform_id' => $platform->id]
                    );

                    for ($tripOrder = 1; $tripOrder <= 6; $tripOrder++) {
                        $driverPrice = round(random_int(600, 900) / 10 - ($tripOrder * 2), 2);

                        $filiale->prices()->firstOrCreate(
                            ['trip_order' => $tripOrder],
                            [
                                'price' => $driverPrice,
                                'owner_price' => round($driverPrice + random_int(150, 300) / 10, 2),
                            ]
                        );
                    }

                    $filialiCreated++;
                }
            }
        }

        $this->command?->info("Filiali demo Vialo create: {$filialiCreated}.");

        $this->seedDemoRule();
    }

    /**
     * Dato di esempio per mostrare il meccanismo delle regole prezzo:
     * i primi 2 veicoli demo diventano motrici da 23 bancali, e una
     * regola fa si' che due viaggi "Secco" di fila su MD paghino di piu'
     * del listino normale.
     */
    private function seedDemoRule(): void
    {
        $mdSecco = GoodsType::query()
            ->whereHas('platform', fn ($q) => $q->where('name', 'MD'))
            ->where('name', 'Secco')
            ->first();

        if (! $mdSecco) {
            return;
        }

        Vehicle::query()->inRandomOrder()->limit(2)->get()->each(
            fn (Vehicle $vehicle) => $vehicle->update(['bancali' => 23])
        );

        $rule = PriceRule::query()->updateOrCreate(
            ['name' => 'Motrice 23 bancali - Secco x2'],
            ['bancali' => 23]
        );

        $rule->steps()->updateOrCreate(['position' => 1], ['goods_type_id' => $mdSecco->id, 'price' => 90, 'owner_price' => 110]);
        $rule->steps()->updateOrCreate(['position' => 2], ['goods_type_id' => $mdSecco->id, 'price' => 90, 'owner_price' => 110]);

        $this->command?->info('Regola prezzo demo creata: ' . $rule->name . '.');
    }
}
