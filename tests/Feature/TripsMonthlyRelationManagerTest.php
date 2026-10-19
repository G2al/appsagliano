<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\RelationManagers\TripsMonthlyRelationManager;
use App\Models\Filiale;
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

    public function test_bordero_records_are_grouped_by_day_with_filiale_shown(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $driver = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $filialeA = Filiale::query()->create(['name' => 'Filiale Nord', 'codice' => 'FN01']);
        $filialeB = Filiale::query()->create(['name' => 'Filiale Sud', 'codice' => 'FS01']);
        $vehicleA = Vehicle::query()->create(['name' => 'Camion A', 'plate' => 'VL001AA', 'current_km' => 0, 'maintenance_km' => 0]);
        $vehicleB = Vehicle::query()->create(['name' => 'Camion B', 'plate' => 'VL002BB', 'current_km' => 0, 'maintenance_km' => 0]);

        $day3 = now()->startOfMonth()->addDays(2);

        Trip::query()->create([
            'user_id' => $driver->id,
            'filiale_id' => $filialeA->id,
            'vehicle_id' => $vehicleA->id,
            'date' => $day3,
            'goods_type' => 'secco',
            'delivery_note_number' => '101',
        ]);

        Trip::query()->create([
            'user_id' => $driver->id,
            'filiale_id' => $filialeB->id,
            'vehicle_id' => $vehicleB->id,
            'date' => $day3->copy()->addHours(3),
            'goods_type' => 'freschi',
            'delivery_note_number' => '102',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(TripsMonthlyRelationManager::class, [
            'ownerRecord' => $driver,
            'pageClass' => EditUser::class,
        ])
            ->assertSuccessful()
            ->assertSee('FN01 - Filiale Nord')
            ->assertSee('FS01 - Filiale Sud')
            ->assertSee('101')
            ->assertSee('102')
            ->assertSee('VL001AA')
            ->assertSee('VL002BB');
    }
}
