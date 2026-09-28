<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorker(): User
    {
        return User::factory()->create([
            'role' => 'worker',
            'is_approved' => true,
        ]);
    }

    private function makeVehicle(string $plate = 'MG001AA'): Vehicle
    {
        return Vehicle::query()->create([
            'name' => 'Camion',
            'plate' => $plate,
            'color' => 'Blu',
            'current_km' => 1000,
            'maintenance_km' => 0,
        ]);
    }

    private function payload(Platform $platform, Vehicle $vehicle, array $overrides = []): array
    {
        return array_merge([
            'date' => '2026-09-29 09:00:00',
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'destinations' => ['Milano', 'Torino'],
            'goods_type' => 'freschi',
            'delivery_note_number' => '12345',
            'attachment' => UploadedFile::fake()->image('bolla.jpg'),
        ], $overrides);
    }

    public function test_worker_can_create_trip_with_multiple_destinations(): void
    {
        Storage::fake('public');

        $worker = $this->makeWorker();
        $platform = Platform::query()->create(['name' => 'Piattaforma Nord']);
        $vehicle = $this->makeVehicle();

        Sanctum::actingAs($worker);

        $response = $this->post('/api/trips', $this->payload($platform, $vehicle));

        $response->assertCreated();

        $trip = Trip::query()->firstOrFail();

        $this->assertSame($worker->id, $trip->user_id);
        $this->assertSame(['Milano', 'Torino'], $trip->destinations);
        $this->assertSame('freschi', $trip->goods_type);
        $this->assertSame('12345', $trip->delivery_note_number);
        $this->assertNull($trip->price);
        $this->assertFalse($trip->is_certified);
        Storage::disk('public')->assertExists($trip->attachment_path);
    }

    public function test_worker_cannot_set_price_when_creating_trip(): void
    {
        Storage::fake('public');

        $worker = $this->makeWorker();
        $platform = Platform::query()->create(['name' => 'Piattaforma Nord']);
        $vehicle = $this->makeVehicle();

        Sanctum::actingAs($worker);

        $this->post('/api/trips', $this->payload($platform, $vehicle, ['price' => 999]))->assertCreated();

        $this->assertNull(Trip::query()->firstOrFail()->price);
    }

    public function test_trip_requires_attachment_bolla_and_a_valid_goods_type(): void
    {
        Storage::fake('public');

        $worker = $this->makeWorker();
        $platform = Platform::query()->create(['name' => 'Piattaforma Nord']);
        $vehicle = $this->makeVehicle();

        Sanctum::actingAs($worker);

        $this->postJson('/api/trips', $this->payload($platform, $vehicle, ['attachment' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('attachment');

        $this->postJson('/api/trips', $this->payload($platform, $vehicle, ['delivery_note_number' => 'ABC']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('delivery_note_number');

        $this->postJson('/api/trips', $this->payload($platform, $vehicle, ['goods_type' => 'surgelati']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('goods_type');

        $this->postJson('/api/trips', $this->payload($platform, $vehicle, ['destinations' => ['', '  ']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('destinations');

        $this->assertDatabaseCount('trips', 0);
    }

    public function test_worker_lists_only_own_trips_while_admin_sees_all(): void
    {
        Storage::fake('public');

        $worker = $this->makeWorker();
        $otherWorker = $this->makeWorker();
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $platform = Platform::query()->create(['name' => 'Piattaforma Nord']);
        $vehicle = $this->makeVehicle();

        foreach ([$worker, $otherWorker] as $user) {
            Trip::query()->create([
                'user_id' => $user->id,
                'platform_id' => $platform->id,
                'vehicle_id' => $vehicle->id,
                'date' => '2026-09-29 09:00:00',
                'destinations' => ['Roma'],
                'goods_type' => 'secco',
                'delivery_note_number' => '1',
                'attachment_path' => 'trips/x.jpg',
            ]);
        }

        Sanctum::actingAs($worker);
        $this->getJson('/api/trips?per_page=all')->assertOk()->assertJsonCount(1);

        Sanctum::actingAs($admin);
        $this->getJson('/api/trips?per_page=all')->assertOk()->assertJsonCount(2);
    }

    public function test_platforms_endpoint_lists_platforms_sorted_by_name(): void
    {
        Platform::query()->create(['name' => 'Zeta']);
        Platform::query()->create(['name' => 'Alfa']);

        Sanctum::actingAs($this->makeWorker());

        $this->getJson('/api/platforms')
            ->assertOk()
            ->assertJsonPath('0.name', 'Alfa')
            ->assertJsonPath('1.name', 'Zeta');
    }

    public function test_trip_is_certified_only_once_price_is_set(): void
    {
        $worker = $this->makeWorker();
        $platform = Platform::query()->create(['name' => 'Piattaforma Nord']);
        $vehicle = $this->makeVehicle();

        $trip = Trip::query()->create([
            'user_id' => $worker->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'date' => '2026-09-29 09:00:00',
            'destinations' => ['Roma'],
            'goods_type' => 'secco',
            'delivery_note_number' => '1',
            'attachment_path' => 'trips/x.jpg',
        ]);

        $this->assertFalse($trip->is_certified);

        $trip->update(['price' => 350.5]);

        $this->assertTrue($trip->fresh()->is_certified);
    }
}
