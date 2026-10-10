<?php

namespace Tests\Feature;

use App\Filament\Pages\TripSchedules;
use App\Livewire\TripScheduleEditor;
use App\Models\Platform;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TripScheduleEditorTest extends TestCase
{
    use RefreshDatabase;

    private function makeDriverWithTrips(): User
    {
        $driver = User::factory()->create(['role' => 'worker', 'is_approved' => true, 'name' => 'Mario', 'surname' => 'Rossi']);
        $platform = Platform::query()->create(['name' => 'Piattaforma Nord']);
        $vehicle = Vehicle::query()->create(['name' => 'Camion', 'plate' => 'MG001AA', 'current_km' => 0, 'maintenance_km' => 0]);

        $day = now()->startOfMonth()->addDays(2);

        Trip::query()->create([
            'user_id' => $driver->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'date' => $day,
            'destinations' => ['Gaeta'],
            'goods_type' => 'secco',
            'delivery_note_number' => '101',
        ]);

        Trip::query()->create([
            'user_id' => $driver->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'date' => $day->copy()->addHours(3),
            'destinations' => ['Napoli', 'Caserta'],
            'goods_type' => 'freschi',
            'delivery_note_number' => '102',
        ]);

        return $driver;
    }

    public function test_trip_schedules_page_requires_admin_or_trips_module(): void
    {
        $outsider = User::factory()->create(['role' => 'worker', 'is_approved' => true, 'panel_modules' => ['manutenzione']]);
        $this->actingAs($outsider);

        $this->assertFalse(TripSchedules::canAccess());

        $tripsUser = User::factory()->create(['role' => 'worker', 'is_approved' => true, 'panel_modules' => [User::PANEL_MODULE_TRIPS]]);
        $this->actingAs($tripsUser);

        $this->assertTrue(TripSchedules::canAccess());
    }

    public function test_setting_a_trip_price_certifies_it_and_updates_day_total(): void
    {
        $driver = $this->makeDriverWithTrips();
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        $gaetaTrip = Trip::where('delivery_note_number', '101')->firstOrFail();

        $component = Livewire::test(TripScheduleEditor::class, ['userId' => $driver->id])
            ->set("prices.{$gaetaTrip->id}", '45.50')
            ->call('updatePrice', $gaetaTrip->id)
            ->assertSuccessful();

        $gaetaTrip->refresh();
        $this->assertSame('45.50', $gaetaTrip->price);
        $this->assertTrue($gaetaTrip->is_certified);

        $component->assertSee('45,50');
    }

    public function test_clearing_a_price_uncertifies_the_trip(): void
    {
        $driver = $this->makeDriverWithTrips();
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);

        $trip = Trip::where('delivery_note_number', '101')->firstOrFail();
        $trip->update(['price' => 30]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(TripScheduleEditor::class, ['userId' => $driver->id])
            ->set("prices.{$trip->id}", '')
            ->call('updatePrice', $trip->id);

        $trip->refresh();
        $this->assertNull($trip->price);
        $this->assertFalse($trip->is_certified);
    }

    public function test_calendar_shows_every_day_of_the_month_even_without_trips(): void
    {
        $driver = $this->makeDriverWithTrips();
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        $emptyDay = now()->startOfMonth()->addDays(20)->day;

        Livewire::test(TripScheduleEditor::class, ['userId' => $driver->id])
            ->assertSuccessful()
            ->assertSee('Gaeta')
            ->assertSee('Napoli → Caserta')
            ->assertSee((string) $emptyDay);
    }

    public function test_changing_the_month_input_changes_the_displayed_trips(): void
    {
        $driver = $this->makeDriverWithTrips();
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        $currentMonth = now()->format('Y-m');
        $nextMonth = now()->addMonthNoOverflow()->format('Y-m');

        $component = Livewire::test(TripScheduleEditor::class, ['userId' => $driver->id])
            ->assertSee('Gaeta');

        $component->set('month', $nextMonth)
            ->assertDontSee('Gaeta');

        $component->set('month', $currentMonth)
            ->assertSee('Gaeta');
    }
}
