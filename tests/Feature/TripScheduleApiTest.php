<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\FakesOpenRouteService;
use Tests\TestCase;

class TripScheduleApiTest extends TestCase
{
    use FakesOpenRouteService;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeOpenRouteServiceSuccess();
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

    public function test_worker_gets_only_own_monthly_schedule(): void
    {
        $worker = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $otherWorker = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $platform = Platform::query()->create(['name' => 'Piattaforma Nord']);
        $vehicle = $this->makeVehicle();

        $day = now()->startOfMonth()->addDays(4);

        Trip::query()->create([
            'user_id' => $worker->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'date' => $day,
            'destinations' => ['Roma'],
            'goods_type' => 'secco',
            'delivery_note_number' => '1',
            'price' => 100,
        ]);

        Trip::query()->create([
            'user_id' => $otherWorker->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'date' => $day,
            'destinations' => ['Napoli'],
            'goods_type' => 'secco',
            'delivery_note_number' => '2',
            'price' => 200,
        ]);

        Sanctum::actingAs($worker);

        $response = $this->getJson('/api/trips/schedule?month=' . now()->format('Y-m'))
            ->assertOk();

        $response->assertJsonPath('total_trips', 1);

        // Il worker non deve mai vedere importi: i totali economici spariscono del tutto.
        $response->assertJsonPath('month_total', null);
        $response->assertJsonPath('platform_totals', null);

        $days = $response->json('days');
        $this->assertCount(now()->daysInMonth, $days);

        $dayWithTrip = collect($days)->firstWhere('day', $day->day);
        $this->assertCount(1, $dayWithTrip['trips']);
        $this->assertNull($dayWithTrip['total']);
        $this->assertArrayNotHasKey('price', $dayWithTrip['trips'][0]);
    }

    public function test_worker_never_receives_price_field_on_trip_endpoints(): void
    {
        $worker = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $platform = Platform::query()->create(['name' => 'Piattaforma Nord']);
        $vehicle = $this->makeVehicle();

        Trip::query()->create([
            'user_id' => $worker->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'date' => now(),
            'destinations' => ['Roma'],
            'goods_type' => 'secco',
            'delivery_note_number' => '1',
            'price' => 999,
        ]);

        Sanctum::actingAs($worker);

        $this->getJson('/api/trips?per_page=all')
            ->assertOk()
            ->assertJsonMissing(['price' => '999.00']);

        $this->getJson('/api/trips?per_page=10')
            ->assertOk()
            ->assertJsonMissing(['price' => '999.00']);
    }

    public function test_worker_cannot_view_another_users_schedule(): void
    {
        $worker = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $otherWorker = User::factory()->create(['role' => 'worker', 'is_approved' => true]);

        Sanctum::actingAs($worker);

        $this->getJson('/api/trips/schedule?user_id=' . $otherWorker->id)
            ->assertForbidden();
    }

    public function test_admin_can_view_another_users_schedule(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $worker = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $platform = Platform::query()->create(['name' => 'Piattaforma Nord']);
        $vehicle = $this->makeVehicle();

        Trip::query()->create([
            'user_id' => $worker->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'date' => now(),
            'destinations' => ['Roma'],
            'goods_type' => 'secco',
            'delivery_note_number' => '1',
            'price' => 50,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/trips/schedule?user_id=' . $worker->id)
            ->assertOk()
            ->assertJsonPath('total_trips', 1);
    }

    public function test_schedule_validates_month_format(): void
    {
        $worker = User::factory()->create(['role' => 'worker', 'is_approved' => true]);

        Sanctum::actingAs($worker);

        $this->getJson('/api/trips/schedule?month=2026-13')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month']);
    }

    public function test_schedule_defaults_to_current_month_when_not_specified(): void
    {
        $worker = User::factory()->create(['role' => 'worker', 'is_approved' => true]);

        Sanctum::actingAs($worker);

        $this->getJson('/api/trips/schedule')
            ->assertOk()
            ->assertJsonPath('month', now()->format('Y-m'));
    }

    public function test_schedule_includes_platform_totals_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $worker = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $platform = Platform::query()->create(['name' => 'Multicedi']);
        $vehicle = $this->makeVehicle();

        Trip::query()->create([
            'user_id' => $worker->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'date' => now(),
            'destinations' => ['Roma'],
            'goods_type' => 'secco',
            'delivery_note_number' => '1',
            'price' => 75,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/trips/schedule?user_id=' . $worker->id)
            ->assertOk()
            ->assertJsonFragment(['platform' => 'Multicedi', 'total' => 75]);
    }
}
