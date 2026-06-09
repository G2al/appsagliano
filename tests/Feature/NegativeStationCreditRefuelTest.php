<?php

namespace Tests\Feature;

use App\Models\Station;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NegativeStationCreditRefuelTest extends TestCase
{
    use RefreshDatabase;

    public function test_refuel_can_be_created_even_if_station_credit_goes_negative(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'role' => 'worker',
            'is_approved' => true,
        ]);

        $station = Station::query()->create([
            'name' => 'Stazione Demo',
            'credit_balance' => 20,
            'uses_vouchers' => false,
        ]);

        $vehicle = Vehicle::query()->create([
            'name' => 'Camion Demo',
            'plate' => 'BD001AA',
            'color' => 'Blu',
            'current_km' => 1000,
            'maintenance_km' => 0,
        ]);

        Sanctum::actingAs($user);

        $response = $this->post('/api/movements', [
            'station_id' => $station->id,
            'vehicle_id' => $vehicle->id,
            'date' => '2026-06-09 09:00:00',
            'km_start' => 1000,
            'km_end' => 1200,
            'liters' => 40,
            'price' => 100,
            'is_voucher' => false,
            'photo' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $response->assertCreated();

        $station->refresh();
        $vehicle->refresh();

        $this->assertSame('-80.00', $station->credit_balance);
        $this->assertSame(1200, $vehicle->current_km);
        $this->assertDatabaseHas('movements', [
            'vehicle_id' => $vehicle->id,
            'station_id' => $station->id,
            'km_start' => 1000,
            'km_end' => 1200,
            'station_charge' => 100.00,
            'is_voucher' => false,
        ]);
    }
}
