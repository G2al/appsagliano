<?php

namespace App\Support;

use App\Models\Trip;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TripExcelExporter
{
    private const HEADERS = [
        'Data', 'Autista', 'Veicolo', 'Piattaforma', 'Filiale', 'Dicitura',
        'Numero bordero', 'Scarichi', 'Prezzo autista (€)', 'Extra', 'Stato',
    ];

    /**
     * @param  Collection<int, Trip>  $trips
     */
    public static function build(string $title, Collection $trips): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($title, 0, 31) ?: 'Report');

        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->getStyle('A1:' . self::lastColumn() . '1')->getFont()->setBold(true);

        $row = 2;

        foreach ($trips as $trip) {
            $sheet->fromArray([
                $trip->date?->format('d/m/Y H:i'),
                $trip->user?->full_name,
                trim(($trip->vehicle?->plate ? $trip->vehicle->plate . ' - ' : '') . ($trip->vehicle?->name ?? '')),
                $trip->filiale?->platform?->name,
                $trip->filiale ? "{$trip->filiale->codice} - {$trip->filiale->name}" : null,
                $trip->filiale?->goodsType?->name,
                $trip->delivery_note_number,
                $trip->scarichi,
                $trip->price !== null ? (float) $trip->price : null,
                $trip->extrasBreakdown()->map(fn (array $item) => "{$item['label']} (+€{$item['amount']})")->implode(', '),
                $trip->is_certified ? 'Certificato' : 'Da certificare',
            ], null, 'A' . $row);

            $row++;
        }

        $totalRow = $row;
        $sheet->setCellValue('H' . $totalRow, 'Totale');
        $sheet->setCellValue('I' . $totalRow, (float) $trips->sum(fn (Trip $trip) => (float) ($trip->price ?? 0)));
        $sheet->getStyle('H' . $totalRow . ':I' . $totalRow)->getFont()->setBold(true);

        foreach (range('A', self::lastColumn()) as $column) {
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

    private static function lastColumn(): string
    {
        return chr(ord('A') + count(self::HEADERS) - 1);
    }
}
