<?php

namespace Database\Seeders;

use App\Models\Filiale;
use App\Models\Maintenance;
use App\Models\Movement;
use App\Models\Platform;
use App\Models\PriceRule;
use App\Models\Station;
use App\Models\Supplier;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TripPriceCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class VialoDemoActivitySeeder extends Seeder
{
    /**
     * Genera record bordero, rifornimenti e manutenzioni realistici usando
     * gli autisti, i veicoli e le piattaforme/filiali GIA' presenti nel
     * database (non crea anagrafica), per avere dati di prova come se
     * l'app fosse stata usata davvero negli ultimi mesi.
     *
     * Non idempotente: ogni esecuzione aggiunge nuovi movimenti/manutenzioni/
     * record bordero. Rilanciarlo piu' volte accumula altri dati demo.
     */
    private const DAYS_BACK = 60;

    private const MAX_DRIVERS = 40;

    public function run(): void
    {
        $calculator = app(TripPriceCalculator::class);

        $drivers = User::query()
            ->where('role', 'worker')
            ->where('is_approved', true)
            ->inRandomOrder()
            ->limit(self::MAX_DRIVERS)
            ->get();

        $vehicles = Vehicle::query()->inRandomOrder()->get();
        $platforms = Platform::query()->with('extras')->get();
        $filiali = Filiale::query()->with(['prices', 'goodsType.dischargeBonuses', 'platform.extras'])->get();
        $stations = Station::query()->get();
        $suppliers = Supplier::query()->get();

        if ($drivers->isEmpty() || $vehicles->isEmpty() || $platforms->isEmpty() || $filiali->isEmpty()) {
            $this->command?->warn('Servono autisti, veicoli, piattaforme e filiali gia presenti nel database: esegui prima i seeder di anagrafica.');

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

                // 1-2 record bordero nel giorno.
                $tripsToday = random_int(1, 2);
                for ($i = 0; $i < $tripsToday; $i++) {
                    $filiale = $filiali->random();
                    $tripDate = $date->copy()->addHours(random_int(6, 18))->addMinutes(random_int(0, 59));
                    $scarichi = random_int(0, 8);

                    // L'80% dei record passati e' gia' certificato (revisionato).
                    $isReviewed = random_int(1, 100) <= 80;

                    $this->createDemoTrip($calculator, $driver, $vehicle, $filiale, $tripDate, $scarichi, (string) $deliveryNoteSequence++, $isReviewed);

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

        // Giorno riservato, fuori dal periodo random sopra, cosi' il veicolo
        // scelto per la dimostrazione non ha gia' altri viaggi quel giorno
        // (altrimenti la sequenza non partirebbe pulita dalla posizione 1).
        $ruleDemoDate = $start->copy()->subDay()->setTime(8, 0);
        $ruleTripsCreated = $this->seedRuleDemoTrips($calculator, $drivers, $filiali, $deliveryNoteSequence, $ruleDemoDate);

        $this->command?->info("Generati: {$tripsCreated} record bordero, {$movementsCreated} rifornimenti, {$maintenancesCreated} manutenzioni su {$drivers->count()} autisti ({$ruleTripsCreated} viaggi dimostrativi delle regole prezzo).");
    }

    /**
     * Crea un record bordero usando la stessa logica reale (numero viaggio
     * autista, listino filiale, supplemento scarichi, regola prezzo per
     * veicolo/bancali se scatta), cosi' i dati demo si comportano esattamente
     * come farebbe l'app.
     */
    private function createDemoTrip(
        TripPriceCalculator $calculator,
        User $driver,
        Vehicle $vehicle,
        Filiale $filiale,
        Carbon $tripDate,
        int $scarichi,
        string $deliveryNoteNumber,
        bool $isReviewed,
        ?int $excludeTripId = null,
    ): Trip {
        $tripOrder = $calculator->resolveTripOrder($driver->id, $tripDate, excludeTripId: $excludeTripId);

        $vehicleDayPosition = $calculator->resolveVehicleDayPosition($vehicle->id, $tripDate, excludeTripId: $excludeTripId);
        $priorSequence = $calculator->resolveVehicleDaySequence($vehicle->id, $tripDate, excludeTripId: $excludeTripId);
        $ruleOverride = $calculator->resolveRuleOverride($vehicle, $filiale, $vehicleDayPosition, $priorSequence);

        $price = $ruleOverride['price'] ?? $calculator->resolvePrice($filiale, $tripOrder);
        $ownerPrice = $ruleOverride['owner_price'] ?? $calculator->resolveOwnerPrice($filiale, $tripOrder, $scarichi);

        $extras = $calculator->resolveAutomaticExtras($filiale, $tripDate);
        $extrasTotal = $calculator->resolveExtrasAmount($extras);

        $trip = Trip::create([
            'user_id' => $driver->id,
            'filiale_id' => $filiale->id,
            'vehicle_id' => $vehicle->id,
            'date' => $tripDate,
            'trip_order' => $tripOrder,
            'scarichi' => $scarichi,
            'delivery_note_number' => $deliveryNoteNumber,
            'price' => $price,
            'owner_price' => $ownerPrice !== null ? round($ownerPrice + $extrasTotal, 2) : null,
            'reviewed_at' => $isReviewed ? $tripDate->copy()->addHours(random_int(1, 48)) : null,
        ]);

        if ($extras->isNotEmpty()) {
            $trip->extras()->sync($extras->pluck('id'));
        }

        return $trip;
    }

    /**
     * Crea qualche viaggio garantito che fa scattare davvero una regola
     * prezzo demo (se presente), cosi' nella demo si vede subito l'effetto
     * senza doverlo sperare dalla casualita' dei dati generati sopra.
     */
    private function seedRuleDemoTrips(TripPriceCalculator $calculator, $drivers, $filiali, int &$deliveryNoteSequence, Carbon $tripDate): int
    {
        $rule = PriceRule::query()->with('steps.goodsType')->first();

        if (! $rule || $rule->steps->isEmpty()) {
            return 0;
        }

        $vehicleQuery = Vehicle::query();
        $vehicle = $rule->bancali !== null
            ? $vehicleQuery->where('bancali', $rule->bancali)->first()
            : $vehicleQuery->first();

        if (! $vehicle) {
            return 0;
        }

        $driver = $drivers->first();

        if (! $driver) {
            return 0;
        }

        $created = 0;

        foreach ($rule->steps as $step) {
            $filiale = $filiali->first(fn (Filiale $f) => $f->goods_type_id === $step->goods_type_id);

            if (! $filiale) {
                continue;
            }

            $this->createDemoTrip(
                $calculator,
                $driver,
                $vehicle,
                $filiale,
                $tripDate->copy()->addHours($step->position),
                scarichi: 0,
                deliveryNoteNumber: 'REGOLA-' . $deliveryNoteSequence++,
                isReviewed: true,
            );

            $created++;
        }

        return $created;
    }
}
