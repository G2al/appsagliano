<?php

namespace Database\Seeders;

use App\Models\Maintenance;
use App\Models\Movement;
use App\Models\Platform;
use App\Models\Station;
use App\Models\Supplier;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class MegaDemoActivitySeeder extends Seeder
{
    /**
     * Genera viaggi, rifornimenti e manutenzioni realistici usando gli
     * autisti, i veicoli e le piattaforme GIA' presenti nel database
     * (non crea anagrafica), per avere dati di prova come se l'app fosse
     * stata usata davvero negli ultimi mesi.
     *
     * Non idempotente: ogni esecuzione aggiunge nuovi movimenti/manutenzioni/
     * viaggi. Rilanciarlo piu' volte accumula altri dati demo.
     */
    private const DAYS_BACK = 60;

    private const MAX_DRIVERS = 40;

    private const DESTINATIONS = [
        'Roma', 'Napoli', 'Salerno', 'Caserta', 'Frignano', 'Milano', 'Bergamo',
        'Mantova', 'Torino', 'Bologna', 'Firenze', 'Bari', 'Latina', 'Frosinone',
        'Avellino', 'Benevento', 'Pescara', 'Foggia', 'Taranto', 'Brindisi',
    ];

    public function run(): void
    {
        $drivers = User::query()
            ->where('role', 'worker')
            ->where('is_approved', true)
            ->inRandomOrder()
            ->limit(self::MAX_DRIVERS)
            ->get();

        $vehicles = Vehicle::query()->inRandomOrder()->get();
        $platforms = Platform::query()->get();
        $stations = Station::query()->get();
        $suppliers = Supplier::query()->get();

        if ($drivers->isEmpty() || $vehicles->isEmpty() || $platforms->isEmpty()) {
            $this->command?->warn('Servono autisti, veicoli e piattaforme gia presenti nel database: esegui prima i seeder di anagrafica.');

            return;
        }

        // Ogni autista "adotta" un veicolo fisso per continuita' (come nella realta').
        $driverVehicle = [];
        foreach ($drivers as $index => $driver) {
            $driverVehicle[$driver->id] = $vehicles[$index % $vehicles->count()];
        }

        $tripsCreated = 0;
        $movementsCreated = 0;
        $maintenancesCreated = 0;
        $deliveryNoteSequence = 1000;

        $start = Carbon::now()->subDays(self::DAYS_BACK)->startOfDay();
        $end = Carbon::now()->startOfDay();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            // Niente movimenti nel weekend per la maggior parte degli autisti (realistico).
            $isWeekend = $date->isWeekend();

            foreach ($drivers as $driver) {
                $worksToday = $isWeekend ? random_int(1, 100) <= 15 : random_int(1, 100) <= 80;

                if (! $worksToday) {
                    continue;
                }

                $vehicle = $driverVehicle[$driver->id];

                // 1-2 viaggi nel giorno.
                $tripsToday = random_int(1, 2);
                for ($i = 0; $i < $tripsToday; $i++) {
                    $platform = $platforms->random();
                    $destinationsCount = random_int(1, 2);
                    $destinations = collect(self::DESTINATIONS)->shuffle()->take($destinationsCount)->values()->all();

                    // L'80% dei viaggi passati e' gia' certificato (prezzo impostato).
                    $isCertified = random_int(1, 100) <= 80;

                    Trip::create([
                        'user_id' => $driver->id,
                        'platform_id' => $platform->id,
                        'vehicle_id' => $vehicle->id,
                        'date' => $date->copy()->addHours(random_int(6, 18))->addMinutes(random_int(0, 59)),
                        'destinations' => $destinations,
                        'goods_type' => random_int(0, 1) === 0 ? Trip::GOODS_TYPE_DRY : Trip::GOODS_TYPE_FRESH,
                        'delivery_note_number' => (string) $deliveryNoteSequence++,
                        'price' => $isCertified ? round(random_int(600, 3500) / 10, 2) : null,
                    ]);

                    $tripsCreated++;
                }

                // Rifornimento circa ogni 4 giorni per veicolo.
                if ($stations->isNotEmpty() && random_int(1, 100) <= 25) {
                    $station = $stations->random();
                    $kmStart = (int) ($vehicle->current_km ?? 0);
                    $kmEnd = $kmStart + random_int(150, 450);
                    $liters = round(random_int(400, 900) / 10, 2);
                    $pricePerLiter = round(random_int(165, 195) / 100, 3);

                    Movement::create([
                        'user_id' => $driver->id,
                        'station_id' => $station->id,
                        'vehicle_id' => $vehicle->id,
                        'platform_id' => $platforms->random()->id,
                        'date' => $date->copy()->addHours(random_int(6, 19)),
                        'km_start' => $kmStart,
                        'km_end' => $kmEnd,
                        'liters' => $liters,
                        'price' => round($liters * $pricePerLiter, 2),
                        'is_voucher' => (bool) $station->uses_vouchers,
                        'adblue' => random_int(0, 100) <= 30 ? round(random_int(50, 200) / 10, 2) : null,
                        'notes' => null,
                    ]);

                    $movementsCreated++;
                }
            }

            // Qualche manutenzione sparsa nel periodo (non ogni giorno).
            if ($suppliers->isNotEmpty() && random_int(1, 100) <= 4) {
                $vehicle = $vehicles->random();
                $supplier = $suppliers->random();
                $driver = $drivers->random();
                $kmCurrent = (int) ($vehicle->current_km ?? random_int(50000, 150000));

                Maintenance::create([
                    'user_id' => $driver->id,
                    'vehicle_id' => $vehicle->id,
                    'supplier_id' => $supplier->id,
                    'date' => $date->copy()->addHours(random_int(8, 17)),
                    'km_current' => $kmCurrent,
                    'km_after' => $kmCurrent + random_int(15000, 30000),
                    'invoice_number' => 'FT-' . $date->format('Ymd') . '-' . random_int(100, 999),
                    'notes' => collect([
                        'Tagliando ordinario',
                        'Sostituzione pneumatici',
                        'Revisione freni',
                        'Cambio olio e filtri',
                        'Controllo impianto elettrico',
                    ])->random(),
                    'attachment_path' => 'demo/no-attachment.pdf',
                ]);

                $maintenancesCreated++;
            }
        }

        $this->command?->info("Generati: {$tripsCreated} viaggi, {$movementsCreated} rifornimenti, {$maintenancesCreated} manutenzioni su {$drivers->count()} autisti.");
    }
}
