<?php

namespace Tests\Feature;

use App\Filament\Pages\TripSchedules;
use App\Livewire\TripScheduleEditor;
use App\Models\Filiale;
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
        $platform = Platform::query()->firstOrCreate(['name' => 'MD']);
        $goodsType = $platform->goodsTypes()->firstOrCreate(['name' => 'Secco']);
        $filiale = Filiale::query()->create([
            'platform_id' => $platform->id,
            'goods_type_id' => $goodsType->id,
            'name' => 'Filiale Nord',
            'codice' => 'FN01',
        ]);
        $vehicle = Vehicle::query()->create(['name' => 'Camion', 'plate' => 'VL001AA', 'current_km' => 0, 'maintenance_km' => 0]);

        $day = now()->startOfMonth()->addDays(2);

        Trip::query()->create([
            'user_id' => $driver->id,
            'filiale_id' => $filiale->id,
            'vehicle_id' => $vehicle->id,
            'date' => $day,
            'delivery_note_number' => '101',
        ]);

        Trip::query()->create([
            'user_id' => $driver->id,
            'filiale_id' => $filiale->id,
            'vehicle_id' => $vehicle->id,
            'date' => $day->copy()->addHours(3),
            'delivery_note_number' => '102',
        ]);

        return $driver;
    }

    public function test_trip_schedules_page_requires_admin_or_bordero_module(): void
    {
        $outsider = User::factory()->create(['role' => 'worker', 'is_approved' => true, 'panel_modules' => ['maintenance']]);
        $this->actingAs($outsider);

        $this->assertFalse(TripSchedules::canAccess());

        $plainWorker = User::factory()->create(['role' => 'worker', 'is_approved' => true, 'panel_modules' => []]);
        $this->actingAs($plainWorker);

        $this->assertFalse(TripSchedules::canAccess());

        $borderoUser = User::factory()->create(['role' => 'worker', 'is_approved' => true, 'panel_modules' => [User::PANEL_MODULE_BORDERO]]);
        $this->actingAs($borderoUser);

        $this->assertTrue(TripSchedules::canAccess());
    }

    public function test_setting_a_trip_price_certifies_it_and_updates_day_total(): void
    {
        $driver = $this->makeDriverWithTrips();
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        $trip101 = Trip::where('delivery_note_number', '101')->firstOrFail();

        $component = Livewire::test(TripScheduleEditor::class, ['userId' => $driver->id])
            ->set("prices.{$trip101->id}", '45.50')
            ->call('updatePrice', $trip101->id)
            ->assertSuccessful();

        $trip101->refresh();
        $this->assertSame('45.50', $trip101->price);
        $this->assertTrue($trip101->is_certified);

        $component->assertSee('45,50');
    }

    public function test_plain_worker_cannot_set_price(): void
    {
        $driver = $this->makeDriverWithTrips();
        $plainWorker = User::factory()->create([
            'role' => 'worker',
            'is_approved' => true,
            'panel_modules' => [],
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($plainWorker);

        $trip101 = Trip::where('delivery_note_number', '101')->firstOrFail();

        Livewire::test(TripScheduleEditor::class, ['userId' => $driver->id])
            ->set("prices.{$trip101->id}", '45.50')
            ->call('updatePrice', $trip101->id)
            ->assertForbidden();

        $this->assertNull($trip101->fresh()->price);
    }

    public function test_worker_with_bordero_module_cannot_set_price(): void
    {
        $driver = $this->makeDriverWithTrips();
        $borderoUser = User::factory()->create([
            'role' => 'worker',
            'is_approved' => true,
            'panel_modules' => [User::PANEL_MODULE_BORDERO],
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($borderoUser);

        $trip101 = Trip::where('delivery_note_number', '101')->firstOrFail();

        Livewire::test(TripScheduleEditor::class, ['userId' => $driver->id])
            ->set("prices.{$trip101->id}", '60.00')
            ->call('updatePrice', $trip101->id)
            ->assertForbidden();

        $this->assertNull($trip101->fresh()->price);
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
            ->assertSee('Filiale Nord')
            ->assertSee('101')
            ->assertSee('102')
            ->assertSee((string) $emptyDay);
    }

    public function test_trip_schedules_page_shows_reviewed_and_unreviewed_counts_per_driver(): void
    {
        $driver = $this->makeDriverWithTrips();
        $trip101 = Trip::where('delivery_note_number', '101')->firstOrFail();
        $trip101->update(['price' => 45.50, 'reviewed_at' => now()]);
        // trip 102 resta senza prezzo e senza revisione.

        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(TripSchedules::class)
            ->assertSuccessful()
            ->assertSee('1 revisionati')
            ->assertSee('1 da revisionare')
            ->assertSee('45,50');
    }

    public function test_admin_can_set_monthly_salary_and_add_adjustments(): void
    {
        $driver = $this->makeDriverWithTrips();
        $driver->update(['default_salary' => 1100]);
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        $trip101 = Trip::where('delivery_note_number', '101')->firstOrFail();
        $trip101->update(['price' => 50]);

        $component = Livewire::test(TripScheduleEditor::class, ['userId' => $driver->id])
            ->assertSet('salaryInput', '1100.00')
            ->set('salaryInput', '1002')
            ->call('saveSalary')
            ->set('newAdjustmentLabel', 'Acconto')
            ->set('newAdjustmentAmount', '-100')
            ->call('addAdjustment')
            ->set('newAdjustmentLabel', 'TFR')
            ->set('newAdjustmentAmount', '+200')
            ->call('addAdjustment');

        $component->assertSee('Acconto')
            ->assertSee('TFR')
            ->assertSee('1.152,00');

        $this->assertDatabaseHas('driver_monthly_salaries', [
            'user_id' => $driver->id,
            'amount' => 1002,
        ]);

        $this->assertDatabaseCount('driver_monthly_adjustments', 2);
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
            ->assertSee('101');

        $component->set('month', $nextMonth)
            ->assertDontSee('101');

        $component->set('month', $currentMonth)
            ->assertSee('101');
    }
}
