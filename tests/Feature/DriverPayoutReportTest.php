<?php

namespace Tests\Feature;

use App\Filament\Pages\DriverPayoutReport;
use App\Models\Filiale;
use App\Models\Platform;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DriverPayoutReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_access_the_report(): void
    {
        $worker = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $this->actingAs($worker);

        $this->assertFalse(DriverPayoutReport::canAccess());

        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $this->actingAs($admin);

        $this->assertTrue(DriverPayoutReport::canAccess());
    }

    public function test_report_groups_driver_totals_by_company_with_iban(): void
    {
        $platform = Platform::query()->create(['name' => 'MD']);
        $goodsType = $platform->goodsTypes()->create(['name' => 'Secco']);
        $filiale = Filiale::query()->create([
            'platform_id' => $platform->id,
            'goods_type_id' => $goodsType->id,
            'name' => 'Filiale Nord',
            'codice' => 'FN01',
        ]);
        $vehicle = Vehicle::query()->create(['name' => 'Camion', 'plate' => 'VL001AA', 'current_km' => 0, 'maintenance_km' => 0]);

        $driver = User::factory()->create([
            'role' => 'worker',
            'is_approved' => true,
            'name' => 'Mario',
            'surname' => 'Rossi',
            'company' => 'Rossi Trasporti SRL',
            'iban' => 'IT60X0542811101000000123456',
        ]);

        Trip::query()->create([
            'user_id' => $driver->id,
            'filiale_id' => $filiale->id,
            'vehicle_id' => $vehicle->id,
            'date' => now()->startOfMonth()->addDays(2),
            'delivery_note_number' => '1',
            'price' => 80,
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(DriverPayoutReport::class)
            ->assertSuccessful()
            ->assertSee('Rossi Trasporti SRL')
            ->assertSee('IT60X0542811101000000123456')
            ->assertSee('Mario Rossi')
            ->assertSee('80,00');
    }

    public function test_each_driver_shows_their_own_iban_even_in_the_same_company(): void
    {
        $platform = Platform::query()->create(['name' => 'MD']);
        $goodsType = $platform->goodsTypes()->create(['name' => 'Secco']);
        $filiale = Filiale::query()->create([
            'platform_id' => $platform->id,
            'goods_type_id' => $goodsType->id,
            'name' => 'Filiale Nord',
            'codice' => 'FN01',
        ]);
        $vehicle = Vehicle::query()->create(['name' => 'Camion', 'plate' => 'VL001AA', 'current_km' => 0, 'maintenance_km' => 0]);

        $driver1 = User::factory()->create([
            'role' => 'worker',
            'is_approved' => true,
            'name' => 'Mario',
            'surname' => 'Rossi',
            'company' => 'Trasporti SRL',
            'iban' => 'IT00AAAA0000000000000000001',
        ]);

        $driver2 = User::factory()->create([
            'role' => 'worker',
            'is_approved' => true,
            'name' => 'Luca',
            'surname' => 'Bianchi',
            'company' => 'Trasporti SRL',
            'iban' => 'IT00BBBB0000000000000000002',
        ]);

        foreach ([$driver1, $driver2] as $i => $driver) {
            Trip::query()->create([
                'user_id' => $driver->id,
                'filiale_id' => $filiale->id,
                'vehicle_id' => $vehicle->id,
                'date' => now()->startOfMonth()->addDays(2),
                'delivery_note_number' => (string) ($i + 1),
                'price' => 80,
            ]);
        }

        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(DriverPayoutReport::class)
            ->assertSuccessful()
            ->assertSee('IT00AAAA0000000000000000001')
            ->assertSee('IT00BBBB0000000000000000002');
    }
}
