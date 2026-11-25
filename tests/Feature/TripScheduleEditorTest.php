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

    public function test_trip_schedules_page_orders_drivers_by_trips_count_descending(): void
    {
        $busyDriver = $this->makeDriverWithTrips(); // 2 trips
        $quietDriver = User::factory()->create(['role' => 'worker', 'is_approved' => true, 'name' => 'Luca', 'surname' => 'Bianchi']);

        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        $users = Livewire::test(TripSchedules::class)->instance()->users;

        $this->assertSame($busyDriver->id, $users->first()->id);
        $this->assertTrue($users->pluck('id')->contains($quietDriver->id));
    }

    public function test_driver_row_shows_as_needing_review_until_admin_marks_it_and_resets_on_new_trip(): void
    {
        $driver = $this->makeDriverWithTrips();
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(TripSchedules::class)
            ->assertSee('Segna visualizzato');

        // Si avanza l'orologio per garantire un distacco reale rispetto alla
        // creazione dei viaggi appena fatta nel setup (altrimenti il confronto
        // di timestamp tra revisione e ultimo viaggio puo' cadere nello stesso secondo).
        $this->travel(1)->minute();

        Livewire::test(TripSchedules::class)
            ->call('markReviewed', $driver->id)
            ->assertDontSee('Segna visualizzato');

        $this->assertNotNull($driver->fresh()->trip_schedule_reviewed_at);

        // Un nuovo viaggio dopo la revisione deve far ritornare l'autista "da visualizzare"
        // (si avanza l'orologio per garantire un distacco reale rispetto alla revisione appena fatta).
        $this->travel(1)->minute();

        Trip::query()->create([
            'user_id' => $driver->id,
            'platform_id' => $driver->trips()->first()->platform_id,
            'vehicle_id' => $driver->trips()->first()->vehicle_id,
            'date' => now(),
            'destinations' => ['Latina'],
            'goods_type' => 'secco',
            'delivery_note_number' => '999',
        ]);

        Livewire::test(TripSchedules::class)
            ->assertSee('Segna visualizzato');
    }

    public function test_only_admin_can_mark_a_driver_as_reviewed(): void
    {
        $driver = $this->makeDriverWithTrips();
        $worker = User::factory()->create(['role' => 'worker', 'is_approved' => true, 'panel_modules' => [User::PANEL_MODULE_TRIPS]]);

        $this->actingAs($worker);

        Livewire::test(TripSchedules::class)
            ->call('markReviewed', $driver->id)
            ->assertForbidden();

        $this->assertNull($driver->fresh()->trip_schedule_reviewed_at);
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
