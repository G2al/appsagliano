<?php

namespace Tests\Feature;

use App\Filament\Resources\VehicleResource\Pages\ListVehicles;
use App\Models\Filiale;
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
        $platform = Platform::query()->firstOrCreate(['name' => 'MD']);
        $goodsType = $platform->goodsTypes()->firstOrCreate(['name' => 'Secco']);
        $filiale = Filiale::query()->create(['platform_id' => $platform->id, 'goods_type_id' => $goodsType->id, 'name' => 'Filiale Nord', 'codice' => 'FN01']);
        $vehicle = Vehicle::query()->create([
            'name' => 'Camion',
            'plate' => 'VL001AA',
            'color' => 'Blu',
            'current_km' => 1000,
            'maintenance_km' => 0,
        ]);

        Trip::query()->create([
            'user_id' => $driver->id,
            'filiale_id' => $filiale->id,
            'vehicle_id' => $vehicle->id,
            'date' => now(),
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
