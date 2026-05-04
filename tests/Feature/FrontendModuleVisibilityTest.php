<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\Movement;
use App\Models\Station;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserDocumentFile;
use App\Models\UserDocumentFolder;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FrontendModuleVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_plain_worker_sees_only_own_movements_in_frontend(): void
    {
        [$workerA, $workerB] = $this->createTwoWorkers();
        $station = Station::create(['name' => 'Eni']);
        $vehicle = Vehicle::create(['name' => 'Scania', 'plate' => 'AA111AA', 'current_km' => 0, 'maintenance_km' => 0]);

        Movement::create([
            'user_id' => $workerA->id,
            'station_id' => $station->id,
            'vehicle_id' => $vehicle->id,
            'date' => now(),
            'km_start' => 1000,
            'km_end' => 1100,
            'liters' => 20,
            'price' => 40,
        ]);

        Movement::create([
            'user_id' => $workerB->id,
            'station_id' => $station->id,
            'vehicle_id' => $vehicle->id,
            'date' => now()->addMinute(),
            'km_start' => 1100,
            'km_end' => 1200,
            'liters' => 22,
            'price' => 44,
        ]);

        Sanctum::actingAs($workerA);

        $response = $this->getJson('/api/movements?per_page=all');

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.user_id', $workerA->id);
    }

    public function test_worker_with_refuels_module_sees_all_movements_in_frontend(): void
    {
        [$workerA, $workerB] = $this->createTwoWorkers();
        $refuelsWorker = User::factory()->create([
            'panel_modules' => [User::PANEL_MODULE_REFUELS],
        ]);

        $station = Station::create(['name' => 'Eni']);
        $vehicle = Vehicle::create(['name' => 'Scania', 'plate' => 'AA111AA', 'current_km' => 0, 'maintenance_km' => 0]);

        foreach ([$workerA, $workerB] as $index => $worker) {
            Movement::create([
                'user_id' => $worker->id,
                'station_id' => $station->id,
                'vehicle_id' => $vehicle->id,
                'date' => now()->addMinutes($index),
                'km_start' => 1000 + ($index * 100),
                'km_end' => 1100 + ($index * 100),
                'liters' => 20 + $index,
                'price' => 40 + $index,
            ]);
        }

        Sanctum::actingAs($refuelsWorker);

        $response = $this->getJson('/api/movements?per_page=all');

        $response
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_worker_with_maintenance_module_sees_all_maintenances_in_frontend(): void
    {
        [$workerA, $workerB] = $this->createTwoWorkers();
        $maintenanceWorker = User::factory()->create([
            'panel_modules' => [User::PANEL_MODULE_MAINTENANCE],
        ]);

        $supplier = Supplier::create(['name' => 'Officina Centro']);
        $vehicle = Vehicle::create(['name' => 'Volvo', 'plate' => 'BB222BB', 'current_km' => 0, 'maintenance_km' => 0]);

        foreach ([$workerA, $workerB] as $index => $worker) {
            Maintenance::create([
                'user_id' => $worker->id,
                'vehicle_id' => $vehicle->id,
                'supplier_id' => $supplier->id,
                'date' => now()->addDays($index),
                'km_current' => 10000 + ($index * 500),
                'km_after' => 15000 + ($index * 500),
                'price' => 100 + $index,
                'invoice_number' => 'BOLLA-' . $index,
                'notes' => 'Cambio olio',
                'attachment_path' => 'maintenances/test-' . $index . '.pdf',
            ]);
        }

        Sanctum::actingAs($maintenanceWorker);

        $response = $this->getJson('/api/maintenances?per_page=all');

        $response
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_plain_worker_sees_only_own_document_folders_in_frontend(): void
    {
        [$workerA, $workerB] = $this->createTwoWorkers();

        $folderA = UserDocumentFolder::create([
            'user_id' => $workerA->id,
            'title' => 'Documenti Mario',
        ]);

        $folderB = UserDocumentFolder::create([
            'user_id' => $workerB->id,
            'title' => 'Documenti Luigi',
        ]);

        Storage::disk('local')->put('documents/a.txt', 'a');
        Storage::disk('local')->put('documents/b.txt', 'b');

        UserDocumentFile::create([
            'user_document_folder_id' => $folderA->id,
            'title' => 'Patente',
            'file_path' => 'documents/a.txt',
        ]);

        UserDocumentFile::create([
            'user_document_folder_id' => $folderB->id,
            'title' => 'CQC',
            'file_path' => 'documents/b.txt',
        ]);

        Sanctum::actingAs($workerA);

        $response = $this->getJson('/api/documents');

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.user_id', $workerA->id);
    }

    public function test_worker_with_documents_module_can_list_and_open_other_users_documents(): void
    {
        [$workerA, $workerB] = $this->createTwoWorkers();
        $documentsWorker = User::factory()->create([
            'panel_modules' => [User::PANEL_MODULE_DOCUMENTS],
        ]);

        $folderA = UserDocumentFolder::create([
            'user_id' => $workerA->id,
            'title' => 'Documenti Mario',
        ]);

        $folderB = UserDocumentFolder::create([
            'user_id' => $workerB->id,
            'title' => 'Documenti Luigi',
        ]);

        Storage::disk('local')->put('documents/a.txt', 'a');
        Storage::disk('local')->put('documents/b.txt', 'b');

        UserDocumentFile::create([
            'user_document_folder_id' => $folderA->id,
            'title' => 'Patente',
            'file_path' => 'documents/a.txt',
        ]);

        $otherFile = UserDocumentFile::create([
            'user_document_folder_id' => $folderB->id,
            'title' => 'CQC',
            'file_path' => 'documents/b.txt',
        ]);

        Sanctum::actingAs($documentsWorker);

        $this->getJson('/api/documents')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('1.owner_name', $workerA->full_name);

        $this->postJson("/api/documents/files/{$otherFile->id}/open", [
            'password' => 'password',
        ])->assertOk();
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function createTwoWorkers(): array
    {
        return [
            User::factory()->create([
                'name' => 'Mario',
                'surname' => 'Rossi',
            ]),
            User::factory()->create([
                'name' => 'Luigi',
                'surname' => 'Verdi',
            ]),
        ];
    }
}
