<?php

namespace Tests\Feature;

use App\Models\Filiale;
use App\Models\GoodsType;
use App\Models\Platform;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripExtrasBreakdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_extras_breakdown_includes_discharge_bonus_and_automatic_extras(): void
    {
        $platform = Platform::query()->create(['name' => 'MD']);
        $goodsType = $platform->goodsTypes()->create(['name' => 'Secco']);
        $goodsType->dischargeBonuses()->create(['min_scarichi' => 2, 'bonus' => 10]);
        $platform->extras()->create(['name' => 'Extra serale', 'amount' => 20]);

        $filiale = Filiale::query()->create([
            'platform_id' => $platform->id,
            'goods_type_id' => $goodsType->id,
            'name' => 'Filiale Nord',
            'codice' => 'FN01',
            'is_serale' => true,
        ]);
        $filiale->prices()->create(['trip_order' => 1, 'price' => 50, 'owner_price' => 70]);

        $driver = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $vehicle = Vehicle::query()->create(['name' => 'Camion', 'plate' => 'VL001AA', 'current_km' => 0, 'maintenance_km' => 0]);

        $trip = Trip::query()->create([
            'user_id' => $driver->id,
            'filiale_id' => $filiale->id,
            'vehicle_id' => $vehicle->id,
            'date' => '2026-09-29 09:00:00',
            'delivery_note_number' => '1',
            'scarichi' => 3,
        ]);
        $trip->extras()->sync([$platform->extras()->first()->id]);

        $breakdown = $trip->fresh(['filiale.goodsType.dischargeBonuses', 'extras'])->extrasBreakdown();

        $this->assertCount(2, $breakdown);
        $this->assertSame('Supplemento scarichi', $breakdown[0]['label']);
        $this->assertSame(10.0, $breakdown[0]['amount']);
        $this->assertSame('Extra serale', $breakdown[1]['label']);
        $this->assertSame(20.0, $breakdown[1]['amount']);
    }
}
