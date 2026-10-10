<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TripDistanceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TripDistanceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function makeTrip(array $overrides = []): Trip
    {
        $worker = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $platform = Platform::query()->create(['name' => 'Apulia', 'address' => 'Via Roma 1, Bari']);
        $vehicle = Vehicle::query()->create([
            'name' => 'Camion', 'plate' => 'MG001AA', 'color' => 'Blu', 'current_km' => 1000, 'maintenance_km' => 0,
        ]);

        return Trip::query()->create(array_merge([
            'user_id' => $worker->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'date' => now(),
            'destinations' => ['Milano', 'Pavia'],
            'goods_type' => 'secco',
            'delivery_note_number' => '1',
        ], $overrides));
    }

    public function test_calculates_distance_and_saves_it_on_the_trip(): void
    {
        Http::fake([
            'api.heigit.org/pelias/*' => Http::response([
                'features' => [[
                    'geometry' => ['coordinates' => [9.19, 45.46]],
                    'properties' => ['confidence' => 1],
                ]],
            ], 200),
            'api.heigit.org/openrouteservice/*' => Http::response([
                'routes' => [['summary' => ['distance' => 150000.0]]],
            ], 200),
        ]);

        $trip = $this->makeTrip();

        app(TripDistanceCalculator::class)->calculate($trip);

        $trip->refresh();

        $this->assertSame('calculated', $trip->distance_status);
        $this->assertEquals(150.0, (float) $trip->distance_km);
        $this->assertNull($trip->distance_note);
        $this->assertNotNull($trip->distance_calculated_at);
    }

    public function test_caches_platform_coordinates_after_first_geocode(): void
    {
        Http::fake([
            'api.heigit.org/pelias/*' => Http::response([
                'features' => [[
                    'geometry' => ['coordinates' => [9.19, 45.46]],
                    'properties' => ['confidence' => 1],
                ]],
            ], 200),
            'api.heigit.org/openrouteservice/*' => Http::response([
                'routes' => [['summary' => ['distance' => 50000.0]]],
            ], 200),
        ]);

        $trip = $this->makeTrip();

        app(TripDistanceCalculator::class)->calculate($trip);

        $platform = $trip->platform()->first();

        $this->assertNotNull($platform->latitude);
        $this->assertNotNull($platform->longitude);

        Http::assertSentCount(4); // geocode piattaforma + 2 destinazioni + directions
    }

    public function test_marks_distance_as_estimated_when_geocoding_confidence_is_low(): void
    {
        Http::fake([
            'api.heigit.org/pelias/*' => Http::response([
                'features' => [[
                    'geometry' => ['coordinates' => [9.19, 45.46]],
                    'properties' => ['confidence' => 0.4],
                ]],
            ], 200),
            'api.heigit.org/openrouteservice/*' => Http::response([
                'routes' => [['summary' => ['distance' => 80000.0]]],
            ], 200),
        ]);

        $trip = $this->makeTrip();

        app(TripDistanceCalculator::class)->calculate($trip);

        $trip->refresh();

        $this->assertSame('estimated', $trip->distance_status);
        $this->assertNotNull($trip->distance_note);
        $this->assertEquals(80.0, (float) $trip->distance_km);
    }

    public function test_marks_distance_as_unavailable_when_directions_api_fails(): void
    {
        Http::fake([
            'api.heigit.org/pelias/*' => Http::response([
                'features' => [[
                    'geometry' => ['coordinates' => [9.19, 45.46]],
                    'properties' => ['confidence' => 1],
                ]],
            ], 200),
            'api.heigit.org/openrouteservice/*' => Http::response([], 500),
        ]);

        $trip = $this->makeTrip();

        app(TripDistanceCalculator::class)->calculate($trip);

        $trip->refresh();

        $this->assertSame('unavailable', $trip->distance_status);
        $this->assertNull($trip->distance_km);
        $this->assertNotNull($trip->distance_note);
    }

    public function test_marks_distance_as_unavailable_when_geocoding_fails(): void
    {
        Http::fake([
            'api.heigit.org/pelias/*' => Http::response([], 503),
            'api.heigit.org/openrouteservice/*' => Http::response([
                'routes' => [['summary' => ['distance' => 80000.0]]],
            ], 200),
        ]);

        $trip = $this->makeTrip();

        app(TripDistanceCalculator::class)->calculate($trip);

        $trip->refresh();

        $this->assertSame('unavailable', $trip->distance_status);
        $this->assertNull($trip->distance_km);
    }

    public function test_marks_distance_as_unavailable_when_platform_has_no_address(): void
    {
        Http::fake();

        $worker = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $platform = Platform::query()->create(['name' => 'Senza indirizzo']);
        $vehicle = Vehicle::query()->create([
            'name' => 'Camion', 'plate' => 'MG002BB', 'color' => 'Blu', 'current_km' => 1000, 'maintenance_km' => 0,
        ]);

        $trip = Trip::query()->create([
            'user_id' => $worker->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'date' => now(),
            'destinations' => ['Milano'],
            'goods_type' => 'secco',
            'delivery_note_number' => '1',
        ]);

        app(TripDistanceCalculator::class)->calculate($trip);

        $trip->refresh();

        $this->assertSame('unavailable', $trip->distance_status);
        Http::assertNothingSent();
    }
}
