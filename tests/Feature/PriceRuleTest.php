<?php

namespace Tests\Feature;

use App\Models\Filiale;
use App\Models\GoodsType;
use App\Models\Platform;
use App\Models\PriceRule;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TripPriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PriceRuleTest extends TestCase
{
    use RefreshDatabase;

    private function makeFiliale(Platform $platform, GoodsType $goodsType, string $codice, float $price = 10, float $ownerPrice = 15): Filiale
    {
        $filiale = Filiale::query()->create([
            'platform_id' => $platform->id,
            'goods_type_id' => $goodsType->id,
            'name' => "Filiale {$codice}",
            'codice' => $codice,
        ]);

        for ($i = 1; $i <= 6; $i++) {
            $filiale->prices()->create(['trip_order' => $i, 'price' => $price, 'owner_price' => $ownerPrice]);
        }

        return $filiale;
    }

    public function test_rule_overrides_default_price_for_matching_vehicle_and_sequence(): void
    {
        $platform = Platform::query()->create(['name' => 'MD']);
        $secco = $platform->goodsTypes()->create(['name' => 'Secco']);
        $filiale = $this->makeFiliale($platform, $secco, 'FN01', price: 10, ownerPrice: 15);

        $vehicle = Vehicle::query()->create(['name' => 'Motrice', 'plate' => 'VL001AA', 'bancali' => 23, 'current_km' => 0, 'maintenance_km' => 0]);
        $driver = User::factory()->create(['role' => 'worker', 'is_approved' => true]);

        $rule = PriceRule::query()->create(['name' => 'Secco x2', 'bancali' => 23]);
        $rule->steps()->create(['position' => 1, 'goods_type_id' => $secco->id, 'price' => 20, 'owner_price' => 25]);
        $rule->steps()->create(['position' => 2, 'goods_type_id' => $secco->id, 'price' => 20, 'owner_price' => 25]);

        $calculator = app(TripPriceCalculator::class);
        $date = Carbon::now();

        // Primo viaggio del veicolo in giornata: deve prendere il prezzo della regola (20), non quello del listino (10).
        $position1 = $calculator->resolveVehicleDayPosition($vehicle->id, $date);
        $sequence1 = $calculator->resolveVehicleDaySequence($vehicle->id, $date);
        $override1 = $calculator->resolveRuleOverride($vehicle, $filiale, $position1, $sequence1);

        $this->assertNotNull($override1);
        $this->assertSame(20.0, $override1['price']);
        $this->assertSame(25.0, $override1['owner_price']);

        Trip::create([
            'user_id' => $driver->id,
            'filiale_id' => $filiale->id,
            'vehicle_id' => $vehicle->id,
            'date' => $date,
            'trip_order' => 1,
            'scarichi' => 0,
            'delivery_note_number' => '1',
            'price' => $override1['price'],
            'owner_price' => $override1['owner_price'],
        ]);

        // Secondo viaggio dello stesso veicolo, stesso giorno, ancora Secco: regola ancora valida (posizione 2).
        $position2 = $calculator->resolveVehicleDayPosition($vehicle->id, $date);
        $sequence2 = $calculator->resolveVehicleDaySequence($vehicle->id, $date);
        $override2 = $calculator->resolveRuleOverride($vehicle, $filiale, $position2, $sequence2);

        $this->assertSame(2, $position2);
        $this->assertNotNull($override2);
        $this->assertSame(20.0, $override2['price']);

        Trip::create([
            'user_id' => $driver->id,
            'filiale_id' => $filiale->id,
            'vehicle_id' => $vehicle->id,
            'date' => $date->copy()->addHour(),
            'trip_order' => 2,
            'scarichi' => 0,
            'delivery_note_number' => '2',
            'price' => $override2['price'],
            'owner_price' => $override2['owner_price'],
        ]);

        // Terzo viaggio: la regola ha solo 2 passaggi, quindi torna al listino normale (10).
        $position3 = $calculator->resolveVehicleDayPosition($vehicle->id, $date);
        $sequence3 = $calculator->resolveVehicleDaySequence($vehicle->id, $date);
        $override3 = $calculator->resolveRuleOverride($vehicle, $filiale, $position3, $sequence3);

        $this->assertSame(3, $position3);
        $this->assertNull($override3);
    }

    public function test_rule_with_specific_bancali_does_not_apply_to_other_vehicles(): void
    {
        $platform = Platform::query()->create(['name' => 'MD']);
        $secco = $platform->goodsTypes()->create(['name' => 'Secco']);
        $filiale = $this->makeFiliale($platform, $secco, 'FN01', price: 10, ownerPrice: 15);

        $vehicleOtherBancali = Vehicle::query()->create(['name' => 'Furgone', 'plate' => 'VL002BB', 'bancali' => 10, 'current_km' => 0, 'maintenance_km' => 0]);

        $rule = PriceRule::query()->create(['name' => 'Secco x2', 'bancali' => 23]);
        $rule->steps()->create(['position' => 1, 'goods_type_id' => $secco->id, 'price' => 20, 'owner_price' => 25]);

        $calculator = app(TripPriceCalculator::class);
        $date = Carbon::now();

        $position = $calculator->resolveVehicleDayPosition($vehicleOtherBancali->id, $date);
        $sequence = $calculator->resolveVehicleDaySequence($vehicleOtherBancali->id, $date);
        $override = $calculator->resolveRuleOverride($vehicleOtherBancali, $filiale, $position, $sequence);

        $this->assertNull($override);
    }

    public function test_rule_without_bancali_applies_to_any_vehicle(): void
    {
        $platform = Platform::query()->create(['name' => 'MD']);
        $secco = $platform->goodsTypes()->create(['name' => 'Secco']);
        $filiale = $this->makeFiliale($platform, $secco, 'FN01', price: 10, ownerPrice: 15);

        $vehicle = Vehicle::query()->create(['name' => 'Camion', 'plate' => 'VL003CC', 'bancali' => null, 'current_km' => 0, 'maintenance_km' => 0]);

        $rule = PriceRule::query()->create(['name' => 'Secco generico', 'bancali' => null]);
        $rule->steps()->create(['position' => 1, 'goods_type_id' => $secco->id, 'price' => 30, 'owner_price' => 35]);

        $calculator = app(TripPriceCalculator::class);
        $date = Carbon::now();

        $position = $calculator->resolveVehicleDayPosition($vehicle->id, $date);
        $sequence = $calculator->resolveVehicleDaySequence($vehicle->id, $date);
        $override = $calculator->resolveRuleOverride($vehicle, $filiale, $position, $sequence);

        $this->assertNotNull($override);
        $this->assertSame(30.0, $override['price']);
    }
}
