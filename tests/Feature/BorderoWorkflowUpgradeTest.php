<?php

namespace Tests\Feature;

use App\Livewire\TripScheduleEditor;
use App\Models\Filiale;
use App\Models\Platform;
use App\Models\Trip;
use App\Models\TripExportBatch;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class BorderoWorkflowUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private function makeDriverWithTrip(?string $deliveryNote = '101', ?string $surname = null): array
    {
        $driver = User::factory()->create(['role' => 'worker', 'is_approved' => true, 'name' => 'Mario', 'surname' => $surname ?? 'Rossi' . $deliveryNote]);
        $platform = Platform::query()->firstOrCreate(['name' => 'MD']);
        $goodsType = $platform->goodsTypes()->firstOrCreate(['name' => 'Secco']);
        $filiale = Filiale::query()->create([
            'platform_id' => $platform->id,
            'goods_type_id' => $goodsType->id,
            'name' => 'Filiale Nord',
            'codice' => 'FN' . $deliveryNote,
        ]);
        $vehicle = Vehicle::query()->create(['name' => 'Camion', 'plate' => 'VL' . $deliveryNote, 'current_km' => 0, 'maintenance_km' => 0]);

        $trip = Trip::query()->create([
            'user_id' => $driver->id,
            'filiale_id' => $filiale->id,
            'vehicle_id' => $vehicle->id,
            'date' => now()->startOfMonth()->addDays(2),
            'delivery_note_number' => $deliveryNote,
            'price' => 50,
            'owner_price' => 70,
        ]);

        return [$driver, $trip, $vehicle];
    }

    public function test_accepting_a_trip_requires_an_attachment_and_records_who_reviewed_it(): void
    {
        [$driver, $trip] = $this->makeDriverWithTrip();
        $reviewer = User::factory()->create(['role' => 'worker', 'is_approved' => true, 'panel_modules' => [User::PANEL_MODULE_BORDERO]]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($reviewer);

        // Senza allegato, non deve accettare.
        Livewire::test(TripScheduleEditor::class, ['userId' => $driver->id])
            ->call('acceptTrip', $trip->id);

        $this->assertNull($trip->fresh()->reviewed_at);

        // Con l'allegato, accetta e registra chi.
        Livewire::test(TripScheduleEditor::class, ['userId' => $driver->id])
            ->set("pendingScans.{$trip->id}", UploadedFile::fake()->image('scan.jpg'))
            ->call('acceptTrip', $trip->id);

        $trip->refresh();
        $this->assertNotNull($trip->reviewed_at);
        $this->assertSame($reviewer->id, $trip->reviewed_by);
        $this->assertTrue($trip->attachments()->exists());
    }

    public function test_exporting_selected_trips_locks_them_for_the_exporting_non_admin_user_only(): void
    {
        [$driver, $trip] = $this->makeDriverWithTrip();
        $reviewer = User::factory()->create(['role' => 'worker', 'is_approved' => true, 'panel_modules' => [User::PANEL_MODULE_BORDERO]]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($reviewer);

        Livewire::test(TripScheduleEditor::class, ['userId' => $driver->id])
            ->call('toggleTripSelection', $trip->id)
            ->call('exportSelected');

        $trip->refresh();
        $this->assertSame($reviewer->id, $trip->export_locked_by);
        $this->assertNotNull($trip->export_locked_at);
        $this->assertTrue($trip->isExportLockedFor($reviewer));

        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $this->assertFalse($trip->isExportLockedFor($admin));

        $this->assertDatabaseCount('trip_export_batches', 1);
        $this->assertSame(1, TripExportBatch::first()->sequence_number);

        // Un secondo giro di esportazione per lo stesso autista incrementa la lista.
        [, $trip2] = $this->makeDriverWithTrip('202');
        $trip2->update(['user_id' => $driver->id]);

        Livewire::test(TripScheduleEditor::class, ['userId' => $driver->id])
            ->call('toggleTripSelection', $trip2->id)
            ->call('exportSelected');

        $this->assertSame(2, TripExportBatch::latest('id')->first()->sequence_number);
    }

    public function test_locked_trip_cannot_be_reaccepted_by_the_worker_who_exported_it_but_admin_is_unaffected(): void
    {
        [$driver, $trip] = $this->makeDriverWithTrip();
        $reviewer = User::factory()->create(['role' => 'worker', 'is_approved' => true, 'panel_modules' => [User::PANEL_MODULE_BORDERO]]);
        $trip->update(['export_locked_by' => $reviewer->id, 'export_locked_at' => now()]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($reviewer);

        Livewire::test(TripScheduleEditor::class, ['userId' => $driver->id])
            ->set("pendingScans.{$trip->id}", UploadedFile::fake()->image('scan.jpg'))
            ->call('acceptTrip', $trip->id)
            ->assertForbidden();
    }

    public function test_non_admin_reviewer_with_assigned_drivers_only_sees_them_in_trip_schedules(): void
    {
        [$assignedDriver] = $this->makeDriverWithTrip('301');
        [$otherDriver] = $this->makeDriverWithTrip('302');

        $reviewer = User::factory()->create(['role' => 'worker', 'is_approved' => true, 'panel_modules' => [User::PANEL_MODULE_BORDERO]]);
        $reviewer->assignedDrivers()->attach($assignedDriver->id);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($reviewer);

        $users = Livewire::test(\App\Filament\Pages\TripSchedules::class)->instance()->users;

        $this->assertTrue($users->pluck('id')->contains($assignedDriver->id));
        $this->assertFalse($users->pluck('id')->contains($otherDriver->id));
    }

    public function test_reviewer_without_any_assigned_drivers_sees_everyone_as_before(): void
    {
        [$driverOne] = $this->makeDriverWithTrip('401');
        [$driverTwo] = $this->makeDriverWithTrip('402');

        $reviewer = User::factory()->create(['role' => 'worker', 'is_approved' => true, 'panel_modules' => [User::PANEL_MODULE_BORDERO]]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($reviewer);

        $users = Livewire::test(\App\Filament\Pages\TripSchedules::class)->instance()->users;

        $this->assertTrue($users->pluck('id')->contains($driverOne->id));
        $this->assertTrue($users->pluck('id')->contains($driverTwo->id));
    }
}
