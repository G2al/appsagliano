<?php

namespace App\Support;

use FPDF;
use Illuminate\Support\Collection;

class DriverPayoutPdfExporter
{
    /**
     * @param  Collection<int, array{company: string, iban: ?string, name: string, trips_total: float, salary: float, adjustments_total: float, total: float}>  $rows
     */
    public static function build(string $month, Collection $rows): string
    {
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'Report pagamenti autisti - ' . $month, 0, 1);
        $pdf->SetFont('Arial', '', 10);

        $byCompany = $rows->groupBy(fn (array $row) => $row['company'] ?: 'Senza societa\'');

        foreach ($byCompany as $company => $companyRows) {
            $pdf->Ln(4);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 8, $company, 0, 1);

            $iban = $companyRows->first()['iban'] ?? null;
            $pdf->SetFont('Arial', 'I', 9);
            $pdf->Cell(0, 6, $iban ? "IBAN: {$iban}" : 'IBAN: non impostato', 0, 1);

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(60, 7, 'Autista', 1);
            $pdf->Cell(35, 7, 'Trasferte (€)', 1, 0, 'R');
            $pdf->Cell(35, 7, 'Stipendio (€)', 1, 0, 'R');
            $pdf->Cell(35, 7, 'Acconti/TFR (€)', 1, 0, 'R');
            $pdf->Cell(35, 7, 'Totale (€)', 1, 1, 'R');

            $pdf->SetFont('Arial', '', 9);
            $companyTotal = 0.0;

            foreach ($companyRows as $row) {
                $pdf->Cell(60, 7, $row['name'], 1);
                $pdf->Cell(35, 7, number_format($row['trips_total'], 2, ',', '.'), 1, 0, 'R');
                $pdf->Cell(35, 7, number_format($row['salary'], 2, ',', '.'), 1, 0, 'R');
                $pdf->Cell(35, 7, number_format($row['adjustments_total'], 2, ',', '.'), 1, 0, 'R');
                $pdf->Cell(35, 7, number_format($row['total'], 2, ',', '.'), 1, 1, 'R');
                $companyTotal += $row['total'];
            }

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(165, 7, 'Totale societa\'', 1);
            $pdf->Cell(35, 7, number_format($companyTotal, 2, ',', '.'), 1, 1, 'R');
        }

        return $pdf->Output('S');
    }
}
