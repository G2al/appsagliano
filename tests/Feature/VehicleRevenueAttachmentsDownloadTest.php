<?php

namespace Tests\Feature;

use App\Filament\Resources\VehicleResource;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRevenue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class VehicleRevenueAttachmentsDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_vehicle_revenue_attachments_as_a_single_pdf_for_a_specific_month(): void
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

        Storage::disk('public')->put(
            'vehicle-revenues/' . $vehicle->id . '/maggio.pdf',
            $this->makePdfFixture('DP528XS maggio')
        );

        Storage::disk('public')->put(
            'vehicle-revenues/' . $vehicle->id . '/giugno.pdf',
            $this->makePdfFixture('DP528XS giugno')
        );

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
        $response->assertHeader('content-type', 'application/pdf');

        $pdfContents = file_get_contents($response->baseResponse->getFile()->getPathname());

        $this->assertStringStartsWith('%PDF', $pdfContents);
        $this->assertStringContainsString('DP528XS maggio', $pdfContents);
        $this->assertStringNotContainsString('DP528XS giugno', $pdfContents);
    }

    public function test_admin_download_orders_attachments_by_vehicle_plate_in_a_single_pdf(): void
    {
        Storage::fake('public');

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

        Storage::disk('public')->put(
            'vehicle-revenues/' . $vehicleZ->id . '/z.pdf',
            $this->makePdfFixture('ZZ999ZZ documento')
        );

        Storage::disk('public')->put(
            'vehicle-revenues/' . $vehicleA->id . '/a.pdf',
            $this->makePdfFixture('AA111AA documento')
        );

        VehicleRevenue::query()->create([
            'vehicle_id' => $vehicleZ->id,
            'date' => '2026-05-10',
            'name' => 'Entrata Z',
            'amount_ex_vat' => 1000,
            'attachment_path' => 'vehicle-revenues/' . $vehicleZ->id . '/z.pdf',
        ]);

        VehicleRevenue::query()->create([
            'vehicle_id' => $vehicleA->id,
            'date' => '2026-05-11',
            'name' => 'Entrata A',
            'amount_ex_vat' => 1000,
            'attachment_path' => 'vehicle-revenues/' . $vehicleA->id . '/a.pdf',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('vehicles.revenues.download', [
                'token' => VehicleResource::buildRevenueDownloadToken([$vehicleZ->id, $vehicleA->id]),
            ]));

        $response->assertOk();
        $response->assertDownload();
        $response->assertHeader('content-type', 'application/pdf');

        $pdfContents = file_get_contents($response->baseResponse->getFile()->getPathname());

        $this->assertStringStartsWith('%PDF', $pdfContents);
        $this->assertStringContainsString('AA111AA documento', $pdfContents);
        $this->assertStringContainsString('ZZ999ZZ documento', $pdfContents);
        $this->assertLessThan(
            strpos($pdfContents, 'ZZ999ZZ documento'),
            strpos($pdfContents, 'AA111AA documento')
        );
    }

    private function makePdfFixture(string $text): string
    {
        $pdf = new Fpdi();
        $pdf->SetCompression(false);
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, $text);

        return $pdf->Output('S');
    }
}
