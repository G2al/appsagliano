<?php

namespace Tests\Feature;

use App\Filament\Resources\VehicleResource;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRevenue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class VehicleRevenueAttachmentsDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_vehicle_revenue_attachments_for_a_specific_month(): void
    {
        Storage::fake('public');

        $user = User::factory()->createQuietly([
            'role' => 'admin',
        ]);

        $vehicle = Vehicle::query()->create([
            'name' => 'Scania R500',
            'plate' => 'DP528XS',
            'color' => 'Blu',
            'current_km' => 0,
            'maintenance_km' => 0,
        ]);

        Storage::disk('public')->put('vehicle-revenues/' . $vehicle->id . '/maggio.pdf', 'file maggio');
        Storage::disk('public')->put('vehicle-revenues/' . $vehicle->id . '/giugno.pdf', 'file giugno');

        VehicleRevenue::query()->create([
            'vehicle_id' => $vehicle->id,
            'date' => '2026-05-10',
            'name' => 'Entrata maggio',
            'amount_ex_vat' => 1000,
            'attachment_path' => 'vehicle-revenues/' . $vehicle->id . '/maggio.pdf',
        ]);

        VehicleRevenue::query()->create([
            'vehicle_id' => $vehicle->id,
            'date' => '2026-06-10',
            'name' => 'Entrata giugno',
            'amount_ex_vat' => 1000,
            'attachment_path' => 'vehicle-revenues/' . $vehicle->id . '/giugno.pdf',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('vehicles.revenues.download', [
                'token' => VehicleResource::buildRevenueDownloadToken([$vehicle->id], '2026-05'),
            ]));

        $response->assertOk();
        $response->assertDownload();

        $entries = $this->readZipEntries($response->baseResponse->getFile()->getPathname());

        $this->assertSame([
            'entrate-veicoli-maggio-2026/DP528XS/2026-05-10_entrata-maggio_1.pdf',
        ], $entries);
    }

    public function test_admin_can_download_all_vehicle_revenue_attachments_grouped_by_vehicle_and_month(): void
    {
        Storage::fake('public');

        $user = User::factory()->createQuietly([
            'role' => 'admin',
        ]);

        $vehicle = Vehicle::query()->create([
            'name' => 'Scania R500',
            'plate' => 'DP528XS',
            'color' => 'Blu',
            'current_km' => 0,
            'maintenance_km' => 0,
        ]);

        Storage::disk('public')->put('vehicle-revenues/' . $vehicle->id . '/maggio.pdf', 'file maggio');
        Storage::disk('public')->put('vehicle-revenues/' . $vehicle->id . '/giugno.pdf', 'file giugno');

        VehicleRevenue::query()->create([
            'vehicle_id' => $vehicle->id,
            'date' => '2026-05-10',
            'name' => 'Entrata maggio',
            'amount_ex_vat' => 1000,
            'attachment_path' => 'vehicle-revenues/' . $vehicle->id . '/maggio.pdf',
        ]);

        VehicleRevenue::query()->create([
            'vehicle_id' => $vehicle->id,
            'date' => '2026-06-10',
            'name' => 'Entrata giugno',
            'amount_ex_vat' => 1000,
            'attachment_path' => 'vehicle-revenues/' . $vehicle->id . '/giugno.pdf',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('vehicles.revenues.download', [
                'token' => VehicleResource::buildRevenueDownloadToken([$vehicle->id]),
            ]));

        $response->assertOk();
        $response->assertDownload();

        $entries = $this->readZipEntries($response->baseResponse->getFile()->getPathname());

        $this->assertSame([
            'entrate-veicoli/DP528XS/giugno-2026/2026-06-10_entrata-giugno_2.pdf',
            'entrate-veicoli/DP528XS/maggio-2026/2026-05-10_entrata-maggio_1.pdf',
        ], $entries);
    }

    private function readZipEntries(string $path): array
    {
        $zip = new ZipArchive();

        $opened = $zip->open($path);

        $this->assertTrue($opened === true, 'Impossibile aprire il file ZIP di test.');

        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entries[] = $zip->getNameIndex($index);
        }

        sort($entries);
        $zip->close();

        return $entries;
    }
}
