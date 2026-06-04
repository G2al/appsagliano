<?php

namespace Tests\Feature;

use App\Models\Movement;
use App\Models\Station;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MovementRealignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfilled_movement_realigns_following_ticket_automatically(): void
    {
        [$user, $station, $vehicle] = $this->makeMovementContext();

        Movement::create([
            'user_id' => $user->id,
            'station_id' => $station->id,
            'vehicle_id' => $vehicle->id,
            'date' => '2026-06-01 08:00:00',
            'km_start' => 1000,
            'km_end' => 1100,
            'liters' => 50,
            'price' => 120,
        ]);

        $todayMovement = Movement::create([
            'user_id' => $user->id,
            'station_id' => $station->id,
            'vehicle_id' => $vehicle->id,
            'date' => '2026-06-03 08:00:00',
            'km_start' => 1100,
            'km_end' => 1400,
            'liters' => 100,
            'price' => 240,
        ]);

        $this->assertSame(1100, $todayMovement->km_start);
        $this->assertSame(3.0, (float) $todayMovement->km_per_liter);

        $yesterdayMovement = Movement::create([
            'user_id' => $user->id,
            'station_id' => $station->id,
            'vehicle_id' => $vehicle->id,
            'date' => '2026-06-02 08:00:00',
            'km_start' => 1100,
            'km_end' => 1200,
            'liters' => 50,
            'price' => 120,
        ]);

        $todayMovement->refresh();
        $yesterdayMovement->refresh();
        $vehicle->refresh();

        $this->assertSame(1100, $yesterdayMovement->km_start);
        $this->assertSame(2.0, (float) $yesterdayMovement->km_per_liter);
        $this->assertSame(1200, $todayMovement->km_start);
        $this->assertSame(2.0, (float) $todayMovement->km_per_liter);
        $this->assertSame(1400, $vehicle->current_km);
    }

    public function test_global_realign_updates_existing_historical_sequence(): void
    {
        [$user, $station, $vehicle] = $this->makeMovementContext();

        $now = now();

        DB::table('movements')->insert([
            [
                'user_id' => $user->id,
                'station_id' => $station->id,
                'vehicle_id' => $vehicle->id,
                'date' => '2026-06-01 08:00:00',
                'km_start' => 1000,
                'km_end' => 1100,
                'liters' => 50,
                'km_per_liter' => 2.00,
                'price' => 120,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $user->id,
                'station_id' => $station->id,
                'vehicle_id' => $vehicle->id,
                'date' => '2026-06-03 08:00:00',
                'km_start' => 1100,
                'km_end' => 1400,
                'liters' => 100,
                'km_per_liter' => 3.00,
                'price' => 240,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $user->id,
                'station_id' => $station->id,
                'vehicle_id' => $vehicle->id,
                'date' => '2026-06-02 08:00:00',
                'km_start' => 1100,
                'km_end' => 1200,
                'liters' => 50,
                'km_per_liter' => 2.00,
                'price' => 120,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $todayMovement = Movement::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('date', '2026-06-02 08:00:00')
            ->firstOrFail();

        $result = Movement::realignAllVehicleSequences();

        $todayMovement->refresh();
        $latestMovement = Movement::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('date', '2026-06-03 08:00:00')
            ->firstOrFail();

        $this->assertSame(1, $result['vehicles']);
        $this->assertSame(3, $result['movements']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1100, $todayMovement->km_start);
        $this->assertSame(2.0, (float) $todayMovement->km_per_liter);
        $this->assertSame(1200, $latestMovement->km_start);
        $this->assertSame(2.0, (float) $latestMovement->km_per_liter);
    }

    /**
     * @return array{0:User,1:Station,2:Vehicle}
     */
    private function makeMovementContext(): array
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        $station = Station::query()->create([
            'name' => 'Stazione Test',
        ]);

        $vehicle = Vehicle::query()->create([
            'name' => 'Veicolo Test',
            'plate' => 'TT001AA',
            'current_km' => 0,
            'maintenance_km' => 0,
        ]);

        return [$user, $station, $vehicle];
    }
}
