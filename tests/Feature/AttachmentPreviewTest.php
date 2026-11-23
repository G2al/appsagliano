<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceResource\Pages\ListMaintenances;
use App\Filament\Resources\MovementResource\Pages\ListMovements;
use App\Filament\Resources\TripResource\Pages\ListTrips;
use App\Models\Maintenance;
use App\Models\Movement;
use App\Models\Platform;
use App\Models\Station;
use App\Models\Supplier;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\FakesOpenRouteService;
use Tests\TestCase;

class AttachmentPreviewTest extends TestCase
{
    use FakesOpenRouteService;
    use RefreshDatabase;

    public function test_trip_attachment_preview_modal_shows_the_file(): void
    {
        $this->fakeOpenRouteServiceSuccess();

        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $driver = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $platform = Platform::query()->create(['name' => 'Piattaforma Nord']);
        $vehicle = Vehicle::query()->create([
            'name' => 'Camion',
            'plate' => 'MG001AA',
            'current_km' => 1000,
            'maintenance_km' => 0,
        ]);

        $trip = Trip::query()->create([
            'user_id' => $driver->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'date' => now(),
            'destinations' => ['Roma'],
            'goods_type' => 'secco',
            'delivery_note_number' => '1',
        ]);
        $trip->attachments()->create(['path' => 'trips/bolla.jpg']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(ListTrips::class)
            ->callTableAction('attachment_preview', $trip)
            ->assertSuccessful();
    }

    public function test_maintenance_attachment_preview_modal_shows_the_file(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $vehicle = Vehicle::query()->create([
            'name' => 'Camion',
            'plate' => 'MG002AA',
            'current_km' => 1000,
            'maintenance_km' => 0,
        ]);
        $supplier = Supplier::query()->create(['name' => 'Officina Rossi']);

        $maintenance = Maintenance::query()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'supplier_id' => $supplier->id,
            'date' => now(),
            'price' => 100,
            'km_current' => 1000,
            'invoice_number' => 'INV-1',
            'notes' => 'Tagliando',
            'attachment_path' => 'maintenances/ricevuta.jpg',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(ListMaintenances::class)
            ->callTableAction('attachment_preview', $maintenance)
            ->assertSuccessful();
    }

    public function test_movement_attachment_preview_modal_shows_the_file(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $vehicle = Vehicle::query()->create([
            'name' => 'Camion',
            'plate' => 'MG003AA',
            'current_km' => 1000,
            'maintenance_km' => 0,
        ]);
        $station = Station::query()->create(['name' => 'Stazione Nord']);

        $movement = Movement::query()->create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'station_id' => $station->id,
            'date' => now(),
            'km_start' => 1000,
            'km_end' => 1100,
            'liters' => 50,
            'price' => 90,
            'photo_path' => 'receipts/ricevuta.jpg',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(ListMovements::class)
            ->callTableAction('attachment_preview', $movement)
            ->assertSuccessful();
    }
}
