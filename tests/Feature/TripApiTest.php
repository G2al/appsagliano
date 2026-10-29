<?php

namespace Tests\Feature;

use App\Models\Filiale;
use App\Models\GoodsType;
use App\Models\Platform;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorker(): User
    {
        return User::factory()->create([
            'role' => 'worker',
            'is_approved' => true,
        ]);
    }

    private function makeVehicle(string $plate = 'VL001AA'): Vehicle
    {
        return Vehicle::query()->create([
            'name' => 'Camion',
            'plate' => $plate,
            'color' => 'Blu',
            'current_km' => 1000,
            'maintenance_km' => 0,
        ]);
    }

    private function makeGoodsType(string $platformName = 'MD', string $goodsTypeName = 'Freschi'): GoodsType
    {
        $platform = Platform::query()->firstOrCreate(['name' => $platformName]);

        return $platform->goodsTypes()->create(['name' => $goodsTypeName]);
    }

    private function makeFiliale(string $codice = 'FN01', ?GoodsType $goodsType = null): Filiale
    {
        $goodsType ??= $this->makeGoodsType();

        return Filiale::query()->create([
            'platform_id' => $goodsType->platform_id,
            'goods_type_id' => $goodsType->id,
            'name' => 'Filiale Nord',
            'codice' => $codice,
        ]);
    }

    private function payload(Filiale $filiale, Vehicle $vehicle, array $overrides = []): array
    {
        return array_merge([
            'date' => '2026-09-29 09:00:00',
            'filiale_id' => $filiale->id,
            'vehicle_id' => $vehicle->id,
            'scarichi' => 3,
            'delivery_note_number' => '12345',
            'attachment' => UploadedFile::fake()->image('bolla.jpg'),
        ], $overrides);
    }

    public function test_worker_can_create_bordero_record(): void
    {
        Storage::fake('public');

        $worker = $this->makeWorker();
        $filiale = $this->makeFiliale();
        $vehicle = $this->makeVehicle();

        Sanctum::actingAs($worker);

        $response = $this->post('/api/trips', $this->payload($filiale, $vehicle));

        $response->assertCreated();

        $trip = Trip::query()->firstOrFail();

        $this->assertSame($worker->id, $trip->user_id);
        $this->assertSame($filiale->id, $trip->filiale_id);
        $this->assertSame('Freschi', $trip->filiale->goodsType->name);
        $this->assertSame(3, $trip->scarichi);
        $this->assertSame('12345', $trip->delivery_note_number);
        $this->assertNull($trip->price);
        $this->assertFalse($trip->is_certified);
        $this->assertCount(1, $trip->attachments);
        Storage::disk('public')->assertExists($trip->attachments->first()->path);
    }

    public function test_worker_cannot_set_price_when_creating_bordero_record(): void
    {
        Storage::fake('public');

        $worker = $this->makeWorker();
        $filiale = $this->makeFiliale();
        $vehicle = $this->makeVehicle();

        Sanctum::actingAs($worker);

        $this->post('/api/trips', $this->payload($filiale, $vehicle, ['price' => 999]))->assertCreated();

        $this->assertNull(Trip::query()->firstOrFail()->price);
    }

    public function test_bordero_record_requires_filiale_and_allows_optional_attachment(): void
    {
        Storage::fake('public');

        $worker = $this->makeWorker();
        $filiale = $this->makeFiliale();
        $vehicle = $this->makeVehicle();

        Sanctum::actingAs($worker);

        $this->postJson('/api/trips', $this->payload($filiale, $vehicle, ['delivery_note_number' => 'ABC']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('delivery_note_number');

        $this->postJson('/api/trips', $this->payload($filiale, $vehicle, ['filiale_id' => 99999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('filiale_id');

        $this->assertDatabaseCount('trips', 0);

        $this->postJson('/api/trips', $this->payload($filiale, $vehicle, ['attachment' => null]))
            ->assertCreated();

        $this->assertCount(0, Trip::query()->firstOrFail()->attachments);
    }

    public function test_delivery_note_number_accepts_digits_and_dashes(): void
    {
        Storage::fake('public');

        $worker = $this->makeWorker();
        $filiale = $this->makeFiliale();
        $vehicle = $this->makeVehicle();

        Sanctum::actingAs($worker);

        $this->postJson('/api/trips', $this->payload($filiale, $vehicle, ['delivery_note_number' => '2332-2332']))
            ->assertCreated();

        $this->assertSame('2332-2332', Trip::query()->firstOrFail()->delivery_note_number);
    }

    public function test_worker_can_upload_multiple_attachments(): void
    {
        Storage::fake('public');

        $worker = $this->makeWorker();
        $filiale = $this->makeFiliale();
        $vehicle = $this->makeVehicle();

        Sanctum::actingAs($worker);

        $payload = $this->payload($filiale, $vehicle);
        unset($payload['attachment']);
        $payload['attachments'] = [
            UploadedFile::fake()->image('bolla1.jpg'),
            UploadedFile::fake()->image('bolla2.jpg'),
        ];

        $this->post('/api/trips', $payload)->assertCreated();

        $trip = Trip::query()->firstOrFail();

        $this->assertCount(2, $trip->attachments);
        foreach ($trip->attachments as $attachment) {
            Storage::disk('public')->assertExists($attachment->path);
        }
    }

    public function test_worker_can_add_and_remove_attachments_on_update(): void
    {
        Storage::fake('public');

        $worker = $this->makeWorker();
        $filiale = $this->makeFiliale();
        $vehicle = $this->makeVehicle();

        Sanctum::actingAs($worker);

        $trip = Trip::query()->create([
            'user_id' => $worker->id,
            'filiale_id' => $filiale->id,
            'vehicle_id' => $vehicle->id,
            'date' => '2026-09-29 09:00:00',
            'delivery_note_number' => '1',
        ]);
        $keptAttachment = $trip->attachments()->create(['path' => 'trips/keep.jpg']);
        $removedAttachment = $trip->attachments()->create(['path' => 'trips/remove.jpg']);

        $this->put("/api/trips/{$trip->id}", [
            'date' => '2026-09-29 09:00:00',
            'filiale_id' => $filiale->id,
            'vehicle_id' => $vehicle->id,
            'delivery_note_number' => '1',
            'remove_attachment_ids' => [$removedAttachment->id],
            'attachments' => [UploadedFile::fake()->image('new.jpg')],
        ])->assertOk();

        $trip->refresh();

        $this->assertCount(2, $trip->attachments);
        $this->assertTrue($trip->attachments->contains('id', $keptAttachment->id));
        $this->assertFalse($trip->attachments->contains('id', $removedAttachment->id));
    }

    public function test_worker_lists_only_own_bordero_records_while_admin_sees_all(): void
    {
        Storage::fake('public');

        $worker = $this->makeWorker();
        $otherWorker = $this->makeWorker();
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $filiale = $this->makeFiliale();
        $vehicle = $this->makeVehicle();

        foreach ([$worker, $otherWorker] as $user) {
            $trip = Trip::query()->create([
                'user_id' => $user->id,
                'filiale_id' => $filiale->id,
                'vehicle_id' => $vehicle->id,
                'date' => '2026-09-29 09:00:00',
                'delivery_note_number' => '1',
            ]);
            $trip->attachments()->create(['path' => 'trips/x.jpg']);
        }

        Sanctum::actingAs($worker);
        $this->getJson('/api/trips?per_page=all')->assertOk()->assertJsonCount(1);

        Sanctum::actingAs($admin);
        $this->getJson('/api/trips?per_page=all')->assertOk()->assertJsonCount(2);
    }

    public function test_filiali_endpoint_lists_filiali_sorted_by_name(): void
    {
        $goodsType = $this->makeGoodsType();
        Filiale::query()->create([
            'platform_id' => $goodsType->platform_id,
            'goods_type_id' => $goodsType->id,
            'name' => 'Zeta',
            'codice' => 'Z01',
        ]);
        Filiale::query()->create([
            'platform_id' => $goodsType->platform_id,
            'goods_type_id' => $goodsType->id,
            'name' => 'Alfa',
            'codice' => 'A01',
        ]);

        Sanctum::actingAs($this->makeWorker());

        $this->getJson('/api/filiali')
            ->assertOk()
            ->assertJsonPath('0.name', 'Alfa')
            ->assertJsonPath('1.name', 'Zeta');
    }

    public function test_platforms_endpoint_lists_platforms_sorted_by_name(): void
    {
        Platform::query()->create(['name' => 'Zeta']);
        Platform::query()->create(['name' => 'Alfa']);

        Sanctum::actingAs($this->makeWorker());

        $this->getJson('/api/platforms')
            ->assertOk()
            ->assertJsonPath('0.name', 'Alfa')
            ->assertJsonPath('1.name', 'Zeta');
    }

    public function test_trip_is_certified_only_once_price_is_set(): void
    {
        $worker = $this->makeWorker();
        $filiale = $this->makeFiliale();
        $vehicle = $this->makeVehicle();

        $trip = Trip::query()->create([
            'user_id' => $worker->id,
            'filiale_id' => $filiale->id,
            'vehicle_id' => $vehicle->id,
            'date' => '2026-09-29 09:00:00',
            'delivery_note_number' => '1',
        ]);

        $this->assertFalse($trip->is_certified);

        $trip->update(['price' => 350.5]);

        $this->assertTrue($trip->fresh()->is_certified);
    }
}
