<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\Movement;
use App\Models\ExtraCost;
use App\Models\TollRoad;
use App\Models\TollRoadExpense;
use App\Models\UserSalary;
use App\Models\Vehicle;
use App\Models\VehicleRevenue;
use App\Models\VatSetting;
use App\Support\FinancialReport;
use App\Support\FinancialReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_revenue_calculates_amount_with_vat_from_current_setting(): void
    {
        VatSetting::query()->update(['percentage' => 10.00]);

        $vehicle = Vehicle::query()->create([
            'name' => 'Camion test',
            'plate' => 'AA123BB',
            'color' => 'Blu',
            'current_km' => 0,
            'maintenance_km' => 0,
        ]);

        $revenue = VehicleRevenue::query()->create([
            'vehicle_id' => $vehicle->id,
            'date' => '2026-05-23',
            'amount_ex_vat' => 1000,
        ]);

        $this->assertSame('10.00', $revenue->fresh()->vat_percentage);
        $this->assertSame('1100.00', $revenue->fresh()->amount_inc_vat);
    }

    public function test_financial_report_summary_and_vehicle_margin_are_calculated_correctly(): void
    {
        $user = \App\Models\User::factory()->createQuietly();

        $vehicleA = Vehicle::query()->create([
            'name' => 'Bilico 1',
            'plate' => 'GB001AA',
            'color' => 'Bianco',
            'current_km' => 0,
            'maintenance_km' => 0,
        ]);

        $vehicleB = Vehicle::query()->create([
            'name' => 'Bilico 2',
            'plate' => 'GB002AA',
            'color' => 'Rosso',
            'current_km' => 0,
            'maintenance_km' => 0,
        ]);

        $supplier = \App\Models\Supplier::query()->create([
            'name' => 'Officina Demo',
        ]);

        Movement::createQuietly([
            'user_id' => $user->id,
            'vehicle_id' => $vehicleA->id,
            'date' => '2026-05-12 10:00:00',
            'price' => 100,
            'liters' => 50,
            'km_start' => 1000,
            'km_end' => 1500,
            'is_voucher' => false,
        ]);

        Movement::createQuietly([
            'user_id' => $user->id,
            'vehicle_id' => $vehicleA->id,
            'date' => '2026-05-20 12:00:00',
            'price' => 50,
            'liters' => 20,
            'km_start' => 1500,
            'km_end' => 1700,
            'is_voucher' => true,
        ]);

        Maintenance::createQuietly([
            'user_id' => $user->id,
            'vehicle_id' => $vehicleA->id,
            'supplier_id' => $supplier->id,
            'date' => '2026-05-18 09:00:00',
            'km_current' => 1700,
            'km_after' => 2500,
            'price' => 80,
            'invoice_number' => 'B001',
            'notes' => 'Cambio filtro',
            'attachment_path' => 'maintenance/test.pdf',
        ]);

        VehicleRevenue::query()->create([
            'vehicle_id' => $vehicleA->id,
            'date' => '2026-05-31',
            'amount_ex_vat' => 2000,
            'vat_percentage' => 22,
        ]);

        VehicleRevenue::query()->create([
            'vehicle_id' => $vehicleB->id,
            'date' => '2026-05-31',
            'amount_ex_vat' => 500,
            'vat_percentage' => 22,
        ]);

        UserSalary::createQuietly([
            'user_id' => $user->id,
            'date' => '2026-05-12',
            'amount' => 1200,
        ]);

        UserSalary::createQuietly([
            'user_id' => $user->id,
            'date' => '2026-05-23',
            'amount' => 300,
        ]);

        $tollRoad = TollRoad::query()->create([
            'name' => 'A1 Firenze',
        ]);

        TollRoadExpense::createQuietly([
            'toll_road_id' => $tollRoad->id,
            'date' => '2026-05-23',
            'amount' => 342,
        ]);

        ExtraCost::query()->create([
            'date' => '2026-05-24',
            'description' => 'PC aziendale',
            'amount' => 200,
        ]);

        $period = FinancialReportPeriod::custom('2026-05-01', '2026-05-31');
        $summary = FinancialReport::summary($period);

        $this->assertSame(2500.0, $summary['revenues_ex_vat_total']);
        $this->assertSame(3050.0, $summary['revenues_inc_vat_total']);
        $this->assertSame(150.0, $summary['refuels_total']);
        $this->assertSame(80.0, $summary['maintenances_total']);
        $this->assertSame(1500.0, $summary['salaries_total']);
        $this->assertSame(342.0, $summary['tolls_total']);
        $this->assertSame(200.0, $summary['extra_costs_total']);
        $this->assertSame(2820.0, $summary['vehicle_margin_total']);
        $this->assertSame(778.0, $summary['net_margin_total']);

        $vehicles = FinancialReport::vehiclePerformanceQuery($period)
            ->orderByDesc('operating_margin')
            ->get()
            ->keyBy('plate');

        $this->assertSame(2210.0, (float) $vehicles['GB001AA']->operating_margin);
        $this->assertSame(610.0, (float) $vehicles['GB002AA']->operating_margin);
    }
}
