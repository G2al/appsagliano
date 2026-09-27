<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\FinancialReport;
use App\Support\FinancialReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VehiclePerformancePdfDownloadController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isAdmin()) {
            abort(403);
        }

        $token = $request->query('token');

        if (! $token) {
            abort(400, 'Token mancante');
        }

        $payload = json_decode(base64_decode($token), true);

        if (! is_array($payload)) {
            abort(400, 'Token non valido');
        }

        $vehicleIds = collect($payload['vehicle_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($vehicleIds)) {
            abort(400, 'Nessun veicolo selezionato');
        }

        $filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];
        $layout = in_array(($payload['layout'] ?? null), ['full', 'revenues'], true)
            ? $payload['layout']
            : 'full';

        $period = FinancialReportPeriod::fromFilters($filters);

        $vehicles = FinancialReport::vehiclePerformanceQuery($period)
            ->whereIn('vehicles.id', $vehicleIds)
            ->orderBy('vehicles.plate')
            ->orderBy('vehicles.name')
            ->get();

        if ($vehicles->isEmpty()) {
            abort(404, 'Nessun dato disponibile per i veicoli selezionati');
        }

        $fileName = $layout === 'revenues'
            ? 'entrate-veicoli-riepilogo_' . now()->format('Y-m-d_H-i-s') . '.pdf'
            : 'performance-veicoli_' . now()->format('Y-m-d_H-i-s') . '.pdf';

        $tempDir = storage_path('app/temp');
        $tempPath = $tempDir . DIRECTORY_SEPARATOR . $fileName;

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $pdf = new \FPDF($layout === 'revenues' ? 'P' : 'L', 'mm', 'A4');
        $pdf->SetCompression(false);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->SetMargins(8, 8, 8);
        $pdf->AddPage();

        $this->renderHeading(
            $pdf,
            $layout,
            $period,
            $vehicles->count(),
            $layout === 'revenues'
                ? [
                    'revenues_ex_vat_total' => (float) $vehicles->sum('revenues_ex_vat_total'),
                    'revenues_inc_vat_total' => (float) $vehicles->sum('revenues_inc_vat_total'),
                ]
                : null,
        );

        if ($layout === 'revenues') {
            $this->renderRevenuesTable($pdf, $vehicles);
        } else {
            $this->renderPerformanceTable($pdf, $vehicles);
        }

        $pdf->Output('F', $tempPath);

        return response()->download($tempPath, $fileName, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }

    private function renderHeading(
        \FPDF $pdf,
        string $layout,
        FinancialReportPeriod $period,
        int $count,
        ?array $totals = null,
    ): void
    {
        $title = $layout === 'revenues'
            ? 'Riepilogo entrate veicoli'
            : 'Performance veicoli';

        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, $this->encode($title), 0, 1);

        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 6, $this->encode('Periodo: ' . $this->formatPeriodLabel($period)), 0, 1);
        $pdf->Cell(0, 6, $this->encode('Veicoli inclusi: ' . number_format($count, 0, ',', '.')), 0, 1);

        if ($layout === 'revenues' && is_array($totals)) {
            $pdf->Cell(0, 6, $this->encode('Totale entrate nette: ' . $this->formatMoney($totals['revenues_ex_vat_total'] ?? 0)), 0, 1);
            $pdf->Cell(0, 6, $this->encode('Totale entrate con IVA: ' . $this->formatMoney($totals['revenues_inc_vat_total'] ?? 0)), 0, 1);
        }

        $pdf->Ln(2);
    }

    private function renderPerformanceTable(\FPDF $pdf, Collection $vehicles): void
    {
        $headers = ['Veicolo', 'Entrate nette', 'Entrate con IVA', 'Rifornimenti', 'Manutenzioni', 'Costi veicolo', 'Margine'];
        $widths = [72, 28, 30, 28, 30, 34, 30];
        $rowHeight = 8;

        $this->renderHeaderRow($pdf, $headers, $widths, $rowHeight);

        foreach ($vehicles as $vehicle) {
            $this->ensureSpaceForRow($pdf, $headers, $widths, $rowHeight);

            $row = [
                $this->limitText($this->formatVehicleLabel($vehicle), 42),
                $this->formatMoney($vehicle->revenues_ex_vat_total),
                $this->formatMoney($vehicle->revenues_inc_vat_total),
                $this->formatMoney($vehicle->refuels_total),
                $this->formatMoney($vehicle->maintenances_total),
                $this->formatMoney($vehicle->vehicle_total_costs),
                $this->formatMoney($vehicle->operating_margin),
            ];

            $this->renderBodyRow($pdf, $row, $widths, $rowHeight);
        }
    }

    private function renderRevenuesTable(\FPDF $pdf, Collection $vehicles): void
    {
        $headers = ['Veicolo', 'Entrate nette', 'Entrate con IVA'];
        $widths = [94, 44, 44];
        $rowHeight = 8;

        $this->renderHeaderRow($pdf, $headers, $widths, $rowHeight);

        foreach ($vehicles as $vehicle) {
            $this->ensureSpaceForRow($pdf, $headers, $widths, $rowHeight);

            $row = [
                $this->limitText($this->formatVehicleLabel($vehicle), 52),
                $this->formatMoney($vehicle->revenues_ex_vat_total),
                $this->formatMoney($vehicle->revenues_inc_vat_total),
            ];

            $this->renderBodyRow($pdf, $row, $widths, $rowHeight);
        }
    }

    private function ensureSpaceForRow(\FPDF $pdf, array $headers, array $widths, float $rowHeight): void
    {
        if ($pdf->GetY() <= ($pdf->GetPageHeight() - 14)) {
            return;
        }

        $pdf->AddPage();
        $this->renderHeaderRow($pdf, $headers, $widths, $rowHeight);
    }

    private function renderHeaderRow(\FPDF $pdf, array $headers, array $widths, float $rowHeight): void
    {
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(235, 235, 235);

        foreach ($headers as $index => $header) {
            $pdf->Cell($widths[$index], $rowHeight, $this->encode($header), 1, 0, 'C', true);
        }

        $pdf->Ln();
    }

    private function renderBodyRow(\FPDF $pdf, array $cells, array $widths, float $rowHeight): void
    {
        $pdf->SetFont('Arial', '', 8);

        foreach ($cells as $index => $cell) {
            $align = $index === 0 ? 'L' : 'R';
            $pdf->Cell($widths[$index], $rowHeight, $this->encode($cell), 1, 0, $align);
        }

        $pdf->Ln();
    }

    private function formatVehicleLabel(object $vehicle): string
    {
        $label = trim(
            (($vehicle->plate ?? null) ? $vehicle->plate . ' - ' : '') .
            ($vehicle->name ?? '')
        );

        return $label !== '' ? $label : 'Veicolo';
    }

    private function formatMoney(mixed $value): string
    {
        return 'EUR ' . number_format((float) ($value ?? 0), 2, ',', '.');
    }

    private function formatPeriodLabel(FinancialReportPeriod $period): string
    {
        if ($period->start === null && $period->end === null) {
            return 'Totale';
        }

        return trim(
            ($period->start ? 'Dal ' . $period->start->format('d/m/Y') : '') .
            ($period->end ? ' al ' . $period->end->format('d/m/Y') : '')
        );
    }

    private function limitText(string $value, int $maxLength): string
    {
        return Str::length($value) > $maxLength
            ? Str::substr($value, 0, $maxLength - 3) . '...'
            : $value;
    }

    private function encode(string $value): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $value) ?: $value;
    }
}
