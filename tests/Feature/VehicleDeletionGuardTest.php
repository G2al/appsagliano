<?php

namespace Tests\Feature;

use App\Filament\Resources\VehicleResource\Pages\ListVehicles;
use App\Models\Platform;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_vehicle_used_by_a_trip_is_blocked_instead_of_crashing(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $driver = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $platform = Platform::query()->create(['name' => 'Piattaforma Nord']);
        $vehicle = Vehicle::query()->create([
            'name' => 'Camion',
            'plate' => 'MG001AA',
            'color' => 'Blu',
            'current_km' => 1000,
            'maintenance_km' => 0,
        ]);

        Trip::query()->create([
            'user_id' => $driver->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'date' => now(),
            'destinations' => ['Roma'],
            'goods_type' => 'secco',
            'delivery_note_number' => '1',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(ListVehicles::class)
            ->callTableAction('delete', $vehicle)
            ->assertNotified();

        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id]);
    }

    public function test_deleting_a_vehicle_without_trips_succeeds(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $vehicle = Vehicle::query()->create([
            'name' => 'Camion',
            'plate' => 'MG002AA',
            'color' => 'Blu',
            'current_km' => 1000,
            'maintenance_km' => 0,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(ListVehicles::class)
            ->callTableAction('delete', $vehicle);

        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
    }
}
