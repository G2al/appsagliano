<?php

namespace Tests\Feature;

use App\Filament\Pages\TripSchedules;
use App\Models\Platform;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DriverTripsZipExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_a_single_driver_as_a_zip_of_monthly_excel_files(): void
    {
        $driver = User::factory()->create(['role' => 'worker', 'is_approved' => true, 'name' => 'Mario', 'surname' => 'Rossi']);
        $platform = Platform::query()->create(['name' => 'Piattaforma Nord']);
        $vehicle = Vehicle::query()->create(['name' => 'Camion', 'plate' => 'MG001AA', 'current_km' => 0, 'maintenance_km' => 0]);

        Trip::query()->create([
            'user_id' => $driver->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'date' => now(),
            'destinations' => ['Gaeta'],
            'goods_type' => 'secco',
            'delivery_note_number' => '101',
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        $response = Livewire::test(TripSchedules::class)->call('exportDriver', $driver->id);

        $response->assertSuccessful();
    }

    public function test_admin_can_export_all_drivers_as_a_single_zip(): void
    {
        $driver = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $platform = Platform::query()->create(['name' => 'Piattaforma Nord']);
        $vehicle = Vehicle::query()->create(['name' => 'Camion', 'plate' => 'MG002AA', 'current_km' => 0, 'maintenance_km' => 0]);

        Trip::query()->create([
            'user_id' => $driver->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'date' => now(),
            'destinations' => ['Gaeta'],
            'goods_type' => 'secco',
            'delivery_note_number' => '201',
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(TripSchedules::class)->call('exportAll')->assertSuccessful();
    }
}
