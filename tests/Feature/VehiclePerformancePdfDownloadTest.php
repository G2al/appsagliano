<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\Movement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRevenue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehiclePerformancePdfDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_full_vehicle_performance_pdf_sorted_by_plate(): void
    {
        $user = User::factory()->createQuietly([
            'role' => 'admin',
        ]);

        $vehicleZ = Vehicle::query()->create([
            'name' => 'Bilico Z',
            'plate' => 'ZZ999ZZ',
            'color' => 'Blu',
            'current_km' => 0,
            'maintenance_km' => 0,
        ]);

        $vehicleA = Vehicle::query()->create([
            'name' => 'Bilico A',
            'plate' => 'AA111AA',
            'color' => 'Bianco',
            'current_km' => 0,
            'maintenance_km' => 0,
        ]);

        $supplier = Supplier::query()->create([
            'name' => 'Officina Test',
        ]);

        VehicleRevenue::query()->create([
            'vehicle_id' => $vehicleZ->id,
            'date' => '2026-05-20',
            'amount_ex_vat' => 1000,
            'vat_percentage' => 22,
        ]);

        VehicleRevenue::query()->create([
            'vehicle_id' => $vehicleA->id,
            'date' => '2026-05-21',
            'amount_ex_vat' => 500,
            'vat_percentage' => 22,
        ]);

        Movement::createQuietly([
            'user_id' => $user->id,
            'vehicle_id' => $vehicleA->id,
            'date' => '2026-05-22 10:00:00',
            'price' => 100,
            'liters' => 50,
            'km_start' => 1000,
            'km_end' => 1500,
            'is_voucher' => false,
        ]);

        Maintenance::createQuietly([
            'user_id' => $user->id,
            'vehicle_id' => $vehicleZ->id,
            'supplier_id' => $supplier->id,
            'date' => '2026-05-23 09:00:00',
            'km_current' => 1500,
            'km_after' => 2500,
            'price' => 80,
            'invoice_number' => 'B001',
            'notes' => 'Controllo',
            'attachment_path' => 'maintenance/test.pdf',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('report-general.vehicle-performance.download', [
                'token' => base64_encode(json_encode([
                    'vehicle_ids' => [$vehicleZ->id, $vehicleA->id],
                    'filters' => [
                        'period_preset' => 'current_month',
                        'start_date' => '2026-05-01',
                        'end_date' => '2026-05-31',
                    ],
                    'layout' => 'full',
                ])),
            ]));

        $response->assertOk();
        $response->assertDownload();
        $response->assertHeader('content-type', 'application/pdf');

        $pdfContents = file_get_contents($response->baseResponse->getFile()->getPathname());

        $this->assertStringStartsWith('%PDF', $pdfContents);
        $this->assertStringContainsString('Performance veicoli', $pdfContents);
        $this->assertStringContainsString('AA111AA - Bilico A', $pdfContents);
        $this->assertStringContainsString('ZZ999ZZ - Bilico Z', $pdfContents);
        $this->assertLessThan(
            strpos($pdfContents, 'ZZ999ZZ - Bilico Z'),
            strpos($pdfContents, 'AA111AA - Bilico A')
        );
    }

    public function test_admin_can_download_compact_vehicle_revenues_pdf_for_selected_vehicles_only(): void
    {
        $user = User::factory()->createQuietly([
            'role' => 'admin',
        ]);

        $vehicleA = Vehicle::query()->create([
            'name' => 'Bilico A',
            'plate' => 'AA111AA',
            'color' => 'Bianco',
            'current_km' => 0,
            'maintenance_km' => 0,
        ]);

        $vehicleB = Vehicle::query()->create([
            'name' => 'Bilico B',
            'plate' => 'BB222BB',
            'color' => 'Rosso',
            'current_km' => 0,
            'maintenance_km' => 0,
        ]);

        VehicleRevenue::query()->create([
            'vehicle_id' => $vehicleA->id,
            'date' => '2026-05-20',
            'amount_ex_vat' => 1000,
            'vat_percentage' => 22,
        ]);

        VehicleRevenue::query()->create([
            'vehicle_id' => $vehicleB->id,
            'date' => '2026-05-21',
            'amount_ex_vat' => 2000,
            'vat_percentage' => 22,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('report-general.vehicle-performance.download', [
                'token' => base64_encode(json_encode([
                    'vehicle_ids' => [$vehicleB->id],
                    'filters' => [
                        'period_preset' => 'current_month',
                        'start_date' => '2026-05-01',
                        'end_date' => '2026-05-31',
                    ],
                    'layout' => 'revenues',
                ])),
            ]));

        $response->assertOk();
        $response->assertDownload();
        $response->assertHeader('content-type', 'application/pdf');

        $pdfContents = file_get_contents($response->baseResponse->getFile()->getPathname());

        $this->assertStringStartsWith('%PDF', $pdfContents);
        $this->assertStringContainsString('Riepilogo entrate veicoli', $pdfContents);
        $this->assertStringContainsString('BB222BB - Bilico B', $pdfContents);
        $this->assertStringNotContainsString('AA111AA - Bilico A', $pdfContents);
    }
}
