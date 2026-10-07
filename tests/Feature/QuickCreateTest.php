<?php

namespace Tests\Feature;

use App\Models\Station;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuickCreateTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorker(): User
    {
        return User::factory()->create(['role' => 'worker', 'is_approved' => true]);
    }

    public function test_worker_can_create_a_vehicle_on_the_fly(): void
    {
        Sanctum::actingAs($this->makeWorker());

        $response = $this->postJson('/api/vehicles', [
            'name' => 'MOTRICE - FRIGO',
            'plate' => 'MG999ZZ',
            'color' => 'Bianco',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('vehicles', ['plate' => 'MG999ZZ', 'current_km' => 0, 'maintenance_km' => 0]);
    }

    public function test_vehicle_plate_must_be_unique(): void
    {
        Vehicle::query()->create([
            'name' => 'Camion',
            'plate' => 'MG001AA',
            'color' => 'Blu',
            'current_km' => 1000,
            'maintenance_km' => 0,
        ]);

        Sanctum::actingAs($this->makeWorker());

        $this->postJson('/api/vehicles', [
            'name' => 'Altro',
            'plate' => 'MG001AA',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plate');
    }

    public function test_worker_can_create_a_card_only_for_a_credit_card_station(): void
    {
        $cardStation = Station::query()->create(['name' => 'Stazione Carte', 'uses_credit_cards' => true]);
        $classicStation = Station::query()->create(['name' => 'Stazione Classica']);

        Sanctum::actingAs($this->makeWorker());

        $this->postJson("/api/stations/{$cardStation->id}/cards", [
            'number' => '1234-5678',
            'label' => 'Carta Mario',
        ])->assertCreated();

        $this->assertDatabaseHas('station_cards', ['station_id' => $cardStation->id, 'number' => '1234-5678']);

        $this->postJson("/api/stations/{$classicStation->id}/cards", [
            'number' => '9999-0000',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('number');
    }

    public function test_card_number_must_be_globally_unique(): void
    {
        $station = Station::query()->create(['name' => 'Stazione Carte', 'uses_credit_cards' => true]);

        Sanctum::actingAs($this->makeWorker());

        $this->postJson("/api/stations/{$station->id}/cards", ['number' => '1111'])->assertCreated();
        $this->postJson("/api/stations/{$station->id}/cards", ['number' => '1111'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('number');
    }
}
