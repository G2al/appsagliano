<?php

namespace Tests\Feature;

use App\Models\Station;
use App\Models\StationCard;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StationCreditCardTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorker(): User
    {
        return User::factory()->create(['role' => 'worker', 'is_approved' => true]);
    }

    private function makeVehicle(): Vehicle
    {
        return Vehicle::query()->create([
            'name' => 'Camion',
            'plate' => 'MG001AA',
            'color' => 'Blu',
            'current_km' => 1000,
            'maintenance_km' => 0,
        ]);
    }

    private function basePayload(Station $station, Vehicle $vehicle, array $overrides = []): array
    {
        return array_merge([
            'station_id' => $station->id,
            'vehicle_id' => $vehicle->id,
            'date' => '2026-10-06 09:00:00',
            'km_start' => 1000,
            'km_end' => 1200,
            'liters' => 40,
            'price' => 100,
            'photo' => UploadedFile::fake()->image('receipt.jpg'),
        ], $overrides);
    }

    public function test_station_cannot_use_vouchers_and_credit_cards_at_the_same_time(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Station::query()->create([
            'name' => 'Stazione Mista',
            'uses_vouchers' => true,
            'uses_credit_cards' => true,
        ]);
    }

    public function test_refuel_at_credit_card_station_requires_a_card(): void
    {
        Storage::fake('public');

        $station = Station::query()->create([
            'name' => 'Stazione Carte',
            'credit_balance' => 1000,
            'uses_credit_cards' => true,
        ]);
        $vehicle = $this->makeVehicle();

        Sanctum::actingAs($this->makeWorker());

        $this->postJson('/api/movements', $this->basePayload($station, $vehicle))
            ->assertStatus(422)
            ->assertJsonValidationErrors('station_card_id');
    }

    public function test_refuel_with_card_from_another_station_is_rejected(): void
    {
        Storage::fake('public');

        $station = Station::query()->create([
            'name' => 'Stazione Carte',
            'credit_balance' => 1000,
            'uses_credit_cards' => true,
        ]);
        $otherStation = Station::query()->create([
            'name' => 'Altra Stazione',
            'uses_credit_cards' => true,
        ]);
        $foreignCard = StationCard::query()->create([
            'station_id' => $otherStation->id,
            'number' => '9999',
        ]);
        $vehicle = $this->makeVehicle();

        Sanctum::actingAs($this->makeWorker());

        $this->postJson('/api/movements', $this->basePayload($station, $vehicle, [
            'station_card_id' => $foreignCard->id,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('station_card_id');
    }

    public function test_refuel_with_valid_card_succeeds_without_touching_station_credit(): void
    {
        Storage::fake('public');

        $station = Station::query()->create([
            'name' => 'Stazione Carte',
            'credit_balance' => 1000,
            'uses_credit_cards' => true,
        ]);
        $card = StationCard::query()->create([
            'station_id' => $station->id,
            'number' => '1234-5678',
            'label' => 'Carta Mario',
        ]);
        $vehicle = $this->makeVehicle();

        Sanctum::actingAs($this->makeWorker());

        $response = $this->postJson('/api/movements', $this->basePayload($station, $vehicle, [
            'station_card_id' => $card->id,
        ]));

        $response->assertCreated();
        $response->assertJsonPath('station_card_id', $card->id);

        $station->refresh();
        $this->assertSame('1000.00', $station->credit_balance);

        $this->assertDatabaseHas('movements', [
            'station_card_id' => $card->id,
            'station_charge' => 0,
        ]);
    }

    public function test_voucher_station_rejects_a_credit_card(): void
    {
        Storage::fake('public');

        $station = Station::query()->create([
            'name' => 'Stazione Buoni',
            'credit_balance' => 1000,
            'uses_vouchers' => true,
        ]);
        $card = StationCard::query()->create([
            'station_id' => $station->id,
            'number' => '1234',
        ]);
        $vehicle = $this->makeVehicle();

        Sanctum::actingAs($this->makeWorker());

        $this->postJson('/api/movements', $this->basePayload($station, $vehicle, [
            'station_card_id' => $card->id,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('station_card_id');
    }

    public function test_stations_endpoint_lists_cards_and_credit_card_flag(): void
    {
        $station = Station::query()->create([
            'name' => 'Stazione Carte',
            'uses_credit_cards' => true,
        ]);
        StationCard::query()->create(['station_id' => $station->id, 'number' => '1111']);
        StationCard::query()->create(['station_id' => $station->id, 'number' => '2222']);

        Sanctum::actingAs($this->makeWorker());

        $response = $this->getJson('/api/stations');

        $response->assertOk();
        $response->assertJsonPath('0.uses_credit_cards', true);
        $response->assertJsonCount(2, '0.cards');
    }
}
