<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\Movement;
use App\Models\Platform;
use App\Models\Station;
use App\Models\Supplier;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkerEditPermissionsTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_worker_can_edit_own_movement_but_not_someone_elses(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $other = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $station = Station::query()->create(['name' => 'Stazione', 'credit_balance' => 1000]);
        $platform = Platform::query()->create(['name' => 'Piattaforma']);
        $vehicle = $this->makeVehicle();

        Sanctum::actingAs($owner);
        $created = $this->postJson('/api/movements', [
            'station_id' => $station->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'date' => '2026-10-07 09:00:00',
            'km_start' => 1000,
            'km_end' => 1200,
            'liters' => 40,
            'price' => 100,
            'photo' => UploadedFile::fake()->image('receipt.jpg'),
        ])->json();

        $station->refresh();
        $this->assertSame('900.00', $station->credit_balance);

        $movementId = $created['id'];

        // L'autore puo modificare: cambia il prezzo, il credito stazione deve riallinearsi.
        $this->putJson("/api/movements/{$movementId}", [
            'station_id' => $station->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'date' => '2026-10-07 10:00:00',
            'km_start' => 1000,
            'km_end' => 1200,
            'liters' => 40,
            'price' => 150,
        ])->assertOk();

        $station->refresh();
        $this->assertSame('850.00', $station->credit_balance);

        // Un altro operaio non puo modificare questo movimento.
        Sanctum::actingAs($other);
        $this->putJson("/api/movements/{$movementId}", [
            'station_id' => $station->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'date' => '2026-10-07 10:00:00',
            'km_start' => 1000,
            'km_end' => 1200,
            'liters' => 40,
            'price' => 200,
        ])->assertForbidden();
    }

    public function test_worker_can_edit_own_maintenance_without_reattaching_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $supplier = Supplier::query()->create(['name' => 'Fornitore']);
        $vehicle = $this->makeVehicle();

        Sanctum::actingAs($user);

        $maintenance = Maintenance::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'supplier_id' => $supplier->id,
            'date' => '2026-10-07 09:00:00',
            'km_current' => 1000,
            'price' => 200,
            'invoice_number' => '1',
            'notes' => 'Tagliando',
            'attachment_path' => 'maintenances/x.jpg',
        ]);

        $this->putJson("/api/maintenances/{$maintenance->id}", [
            'vehicle_id' => $vehicle->id,
            'supplier_id' => $supplier->id,
            'date' => '2026-10-07 09:00:00',
            'km' => 1000,
            'price' => 250,
            'invoice_number' => '1',
            'notes' => 'Tagliando aggiornato',
        ])->assertOk();

        $maintenance->refresh();
        $this->assertSame('250.00', $maintenance->price);
        $this->assertSame('maintenances/x.jpg', $maintenance->attachment_path);
    }

    public function test_worker_cannot_edit_trip_once_certified_but_admin_can(): void
    {
        Storage::fake('public');

        $worker = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $platform = Platform::query()->create(['name' => 'Piattaforma']);
        $vehicle = $this->makeVehicle();

        $trip = Trip::query()->create([
            'user_id' => $worker->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'date' => '2026-10-07 09:00:00',
            'destinations' => ['Roma'],
            'goods_type' => 'secco',
            'delivery_note_number' => '1',
        ]);

        Sanctum::actingAs($worker);

        $this->putJson("/api/trips/{$trip->id}", [
            'date' => '2026-10-07 09:00:00',
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'destinations' => ['Napoli'],
            'goods_type' => 'freschi',
            'delivery_note_number' => '2',
        ])->assertOk();

        $trip->update(['price' => 300]);
        $this->assertTrue($trip->fresh()->is_certified);

        $this->putJson("/api/trips/{$trip->id}", [
            'date' => '2026-10-07 09:00:00',
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'destinations' => ['Torino'],
            'goods_type' => 'secco',
            'delivery_note_number' => '3',
        ])->assertForbidden();

        Sanctum::actingAs($admin);
        $this->putJson("/api/trips/{$trip->id}", [
            'date' => '2026-10-07 09:00:00',
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'destinations' => ['Torino'],
            'goods_type' => 'secco',
            'delivery_note_number' => '3',
        ])->assertOk();

        $this->assertSame(['Torino'], $trip->fresh()->destinations);
    }
}
