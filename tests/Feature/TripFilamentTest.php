<?php

namespace Tests\Feature;

use App\Filament\Pages\ReportTrips;
use App\Filament\Resources\PlatformResource\Pages\ListPlatforms;
use App\Filament\Resources\TripResource\Pages\EditTrip;
use App\Filament\Resources\TripResource\Pages\ListTrips;
use App\Filament\Widgets\TripsByDriverTable;
use App\Filament\Widgets\TripsByVehicleDriverTable;
use App\Filament\Widgets\TripsByVehicleTable;
use App\Filament\Widgets\TripsListTable;
use App\Filament\Widgets\TripsStats;
use App\Models\Platform;
use App\Models\ReportTableCheck;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TripFilamentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Trip $certifiedTrip;
    private Trip $pendingTrip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $driver = User::factory()->create(['role' => 'worker', 'is_approved' => true]);
        $platform = Platform::query()->create(['name' => 'Piattaforma Nord']);
        $vehicle = Vehicle::query()->create([
            'name' => 'Camion',
            'plate' => 'MG001AA',
            'color' => 'Blu',
            'current_km' => 1000,
            'maintenance_km' => 0,
        ]);

        $base = [
            'user_id' => $driver->id,
            'platform_id' => $platform->id,
            'vehicle_id' => $vehicle->id,
            'date' => now(),
            'destinations' => ['Roma', 'Napoli'],
            'goods_type' => 'secco',
        ];

        $this->certifiedTrip = Trip::query()->create($base + ['delivery_note_number' => '1', 'price' => 400]);
        $this->pendingTrip = Trip::query()->create($base + ['delivery_note_number' => '2']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
    }

    public function test_trip_resource_and_platform_resource_pages_render(): void
    {
        Livewire::test(ListTrips::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$this->certifiedTrip, $this->pendingTrip]);

        Livewire::test(ListPlatforms::class)->assertSuccessful();
    }

    public function test_admin_can_certify_a_trip_by_setting_the_price(): void
    {
        Livewire::test(ListTrips::class)
            ->callTableAction('set_price', $this->pendingTrip, data: ['price' => 250.5])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($this->pendingTrip->fresh()->is_certified);
        $this->assertSame('250.50', $this->pendingTrip->fresh()->price);
    }

    public function test_admin_can_add_and_remove_attachments_when_editing_a_trip(): void
    {
        Storage::fake('public');

        $kept = $this->pendingTrip->attachments()->create(['path' => 'trips/keep.jpg']);
        $this->pendingTrip->attachments()->create(['path' => 'trips/drop.jpg']);

        Livewire::test(EditTrip::class, ['record' => $this->pendingTrip->getRouteKey()])
            ->fillForm([
                'attachments' => [
                    $kept->path,
                    UploadedFile::fake()->image('new.jpg')->store('trips', 'public'),
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->pendingTrip->refresh();

        $this->assertCount(2, $this->pendingTrip->attachments);
        $this->assertTrue($this->pendingTrip->attachments->contains('path', $kept->path));
        $this->assertFalse($this->pendingTrip->attachments->contains('path', 'trips/drop.jpg'));
    }

    public function test_report_page_and_widgets_render_and_filter_checked_rows(): void
    {
        Livewire::test(ReportTrips::class)->assertSuccessful();
        Livewire::test(TripsStats::class)->assertSuccessful();

        foreach ([TripsByVehicleTable::class, TripsByDriverTable::class, TripsByVehicleDriverTable::class] as $widget) {
            Livewire::test($widget)->assertSuccessful();
        }

        $filterKey = now()->startOfMonth()->toDateString() . '|' . now()->toDateString();

        ReportTableCheck::query()->create([
            'user_id' => $this->admin->id,
            'table_key' => TripsListTable::class,
            'filter_key' => $filterKey,
            'row_key' => 'trip:' . $this->certifiedTrip->id,
            'checked_at' => now(),
        ]);

        ReportTableCheck::query()->create([
            'user_id' => $this->admin->id,
            'table_key' => TripsByVehicleDriverTable::class,
            'filter_key' => $filterKey,
            'row_key' => 'vehicle:' . $this->certifiedTrip->vehicle_id . '|user:' . $this->certifiedTrip->user_id,
            'checked_at' => now(),
        ]);

        Livewire::test(TripsByVehicleDriverTable::class)
            ->assertCanSeeTableRecords([$this->certifiedTrip])
            ->filterTable('report_table_checked', 'checked')
            ->assertCanSeeTableRecords([$this->certifiedTrip])
            ->filterTable('report_table_checked', 'unchecked')
            ->assertCanNotSeeTableRecords([$this->certifiedTrip]);

        Livewire::test(TripsListTable::class)
            ->assertCanSeeTableRecords([$this->certifiedTrip, $this->pendingTrip])
            ->filterTable('report_table_checked', 'checked')
            ->assertCanSeeTableRecords([$this->certifiedTrip])
            ->assertCanNotSeeTableRecords([$this->pendingTrip])
            ->filterTable('report_table_checked', 'unchecked')
            ->assertCanSeeTableRecords([$this->pendingTrip])
            ->assertCanNotSeeTableRecords([$this->certifiedTrip]);
    }
}
