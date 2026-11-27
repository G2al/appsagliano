<?php

namespace App\Support;

use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class VehicleTripListExcelExporter
{
    private const HEADERS = [
        'N. Bolla', 'Data', 'Codice filiale', 'Scarichi', 'Extra', 'Guadagno (€)', 'Autista',
    ];

    /**
     * @param  Collection<int, Trip>  $trips
     */
    public static function build(Vehicle $vehicle, Collection $trips): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Lista viaggi');

        $sheet->setCellValue('B1', $vehicle->company ?: 'Societa\' non indicata');
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(16);

        $sheet->setCellValue('B2', $vehicle->plate ?: 'N/D');
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(12);

        $sheet->setCellValue('B4', 'LISTA VIAGGI');
        $sheet->getStyle('B4')->getFont()->setBold(true)->setSize(14);

        $headerRow = 6;
        $sheet->fromArray(self::HEADERS, null, 'B' . $headerRow);
        $sheet->getStyle('B' . $headerRow . ':H' . $headerRow)->getFont()->setBold(true);
        $sheet->getStyle('B' . $headerRow . ':H' . $headerRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E1F2');

        $row = $headerRow + 1;

        foreach ($trips as $trip) {
            $extraLabel = $trip->extras->isNotEmpty()
                ? $trip->extras->pluck('name')->implode(', ')
                : '';

            $driverSurname = $trip->user?->surname ?? 'N/D';

            $sheet->fromArray([
                $trip->delivery_note_number,
                $trip->date?->format('d/m'),
                $trip->filiale ? "{$trip->filiale->codice} - {$trip->filiale->name}" : 'N/D',
                $trip->scarichi,
                $extraLabel,
                $trip->owner_price !== null ? (float) $trip->owner_price : null,
                $driverSurname,
            ], null, 'B' . $row);

            $row++;
        }

        $totalRow = $row;
        $sheet->setCellValue('F' . $totalRow, 'Totale');
        $sheet->setCellValue('G' . $totalRow, (float) $trips->sum(fn (Trip $t) => (float) ($t->owner_price ?? 0)));
        $sheet->getStyle('F' . $totalRow . ':G' . $totalRow)->getFont()->setBold(true);

        foreach (range('B', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    public static function stream(Spreadsheet $spreadsheet, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
