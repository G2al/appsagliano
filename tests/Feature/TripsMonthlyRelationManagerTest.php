<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\RelationManagers\TripsMonthlyRelationManager;
use App\Models\Platform;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TripsMonthlyRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_trips_are_grouped_by_day_with_arrow_joined_destinations(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $driver = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $platform = Platform::query()->create(['name' => 'Piattaforma Nord']);
        $vehicleA = Vehicle::query()->create(['name' => 'Camion A', 'plate' => 'MG001AA', 'current_km' => 0, 'maintenance_km' => 0]);
        $vehicleB = Vehicle::query()->create(['name' => 'Camion B', 'plate' => 'MG002BB', 'current_km' => 0, 'maintenance_km' => 0]);

        $day3 = now()->startOfMonth()->addDays(2);

        Trip::query()->create([
            'user_id' => $driver->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicleA->id,
            'date' => $day3,
            'destinations' => ['Gaeta'],
            'goods_type' => 'secco',
            'delivery_note_number' => '101',
            'attachment_path' => 'trips/a.jpg',
        ]);

        Trip::query()->create([
            'user_id' => $driver->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicleB->id,
            'date' => $day3->copy()->addHours(3),
            'destinations' => ['Napoli', 'Caserta'],
            'goods_type' => 'freschi',
            'delivery_note_number' => '102',
            'attachment_path' => 'trips/b.jpg',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(TripsMonthlyRelationManager::class, [
            'ownerRecord' => $driver,
            'pageClass' => EditUser::class,
        ])
            ->assertSuccessful()
            ->assertSee('Gaeta')
            ->assertSee('Napoli → Caserta')
            ->assertSee('101')
            ->assertSee('102')
            ->assertSee('MG001AA')
            ->assertSee('MG002BB');
    }
}
