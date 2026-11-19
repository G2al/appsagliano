<?php

namespace Tests\Feature;

use App\Models\Filiale;
use App\Models\GoodsType;
use App\Models\Platform;
use App\Models\PlatformExtra;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AutomaticExtrasTest extends TestCase
{
    use RefreshDatabase;

    private function makeFiliale(bool $serale, Platform $platform, GoodsType $goodsType): Filiale
    {
        return Filiale::query()->create([
            'platform_id' => $platform->id,
            'goods_type_id' => $goodsType->id,
            'name' => 'Filiale Nord',
            'codice' => 'FN01',
            'is_serale' => $serale,
        ]);
    }

    private function makeVehicle(): Vehicle
    {
        return Vehicle::query()->create([
            'name' => 'Camion',
            'plate' => 'VL001AA',
            'current_km' => 0,
            'maintenance_km' => 0,
        ]);
    }

    public function test_serale_filiale_adds_extra_serale_to_owner_price_not_to_driver_price(): void
    {
        Storage::fake('public');

        $platform = Platform::query()->create(['name' => 'MD']);
        $goodsType = $platform->goodsTypes()->create(['name' => 'Secco']);
        $platform->extras()->create(['name' => 'Extra serale', 'amount' => 20]);

        $filiale = $this->makeFiliale(true, $platform, $goodsType);
        $filiale->prices()->create(['trip_order' => 1, 'price' => 50, 'owner_price' => 70]);

        $worker = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $vehicle = $this->makeVehicle();

        Sanctum::actingAs($worker);

        // Un martedi' feriale qualsiasi, cosi' non scatta anche l'extra festivo.
        $response = $this->postJson('/api/trips', [
            'date' => '2026-09-29 09:00:00',
            'filiale_id' => $filiale->id,
            'vehicle_id' => $vehicle->id,
            'scarichi' => 0,
            'delivery_note_number' => '1',
            'attachment' => UploadedFile::fake()->image('bolla.jpg'),
        ]);

        $response->assertCreated();

        $trip = Trip::query()->firstOrFail();

        $this->assertSame('50.00', $trip->price);
        $this->assertSame('90.00', $trip->owner_price);
        $this->assertCount(1, $trip->extras);
        $this->assertSame('Extra serale', $trip->extras->first()->name);
    }

    public function test_non_serale_filiale_does_not_add_extra_serale(): void
    {
        Storage::fake('public');

        $platform = Platform::query()->create(['name' => 'MD']);
        $goodsType = $platform->goodsTypes()->create(['name' => 'Secco']);
        $platform->extras()->create(['name' => 'Extra serale', 'amount' => 20]);

        $filiale = $this->makeFiliale(false, $platform, $goodsType);
        $filiale->prices()->create(['trip_order' => 1, 'price' => 50, 'owner_price' => 70]);

        $worker = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $vehicle = $this->makeVehicle();

        Sanctum::actingAs($worker);

        $response = $this->postJson('/api/trips', [
            'date' => '2026-09-29 09:00:00',
            'filiale_id' => $filiale->id,
            'vehicle_id' => $vehicle->id,
            'scarichi' => 0,
            'delivery_note_number' => '1',
            'attachment' => UploadedFile::fake()->image('bolla.jpg'),
        ]);

        $response->assertCreated();

        $trip = Trip::query()->firstOrFail();

        $this->assertSame('70.00', $trip->owner_price);
        $this->assertCount(0, $trip->extras);
    }

    public function test_sunday_trip_adds_extra_festivo_automatically(): void
    {
        Storage::fake('public');

        $platform = Platform::query()->create(['name' => 'MD']);
        $goodsType = $platform->goodsTypes()->create(['name' => 'Secco']);
        $platform->extras()->create(['name' => 'Extra festivo', 'amount' => 30]);

        $filiale = $this->makeFiliale(false, $platform, $goodsType);
        $filiale->prices()->create(['trip_order' => 1, 'price' => 50, 'owner_price' => 70]);

        $worker = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $vehicle = $this->makeVehicle();

        Sanctum::actingAs($worker);

        // 2026-09-27 e' una domenica.
        $response = $this->postJson('/api/trips', [
            'date' => '2026-09-27 09:00:00',
            'filiale_id' => $filiale->id,
            'vehicle_id' => $vehicle->id,
            'scarichi' => 0,
            'delivery_note_number' => '1',
            'attachment' => UploadedFile::fake()->image('bolla.jpg'),
        ]);

        $response->assertCreated();

        $trip = Trip::query()->firstOrFail();

        $this->assertSame('100.00', $trip->owner_price);
        $this->assertCount(1, $trip->extras);
        $this->assertSame('Extra festivo', $trip->extras->first()->name);
    }
}
