<?php

namespace App\Support;

use App\Models\Trip;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TripExcelExporter
{
    private const HEADERS = [
        'Data', 'Autista', 'Veicolo', 'Piattaforma', 'Destinazioni', 'Dicitura',
        'Bolla', 'Km percorsi', 'Prezzo (€)',
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
                $trip->platform?->name,
                $trip->destinations_label,
                Trip::goodsTypeOptions()[$trip->goods_type] ?? $trip->goods_type,
                $trip->delivery_note_number,
                $trip->distance_km !== null ? (float) $trip->distance_km : null,
                $trip->price !== null ? (float) $trip->price : null,
            ], null, 'A' . $row);

            $row++;
        }

        $totalRow = $row;
        $sheet->setCellValue('G' . $totalRow, 'Totale');
        $sheet->setCellValue('H' . $totalRow, (float) $trips->sum(fn (Trip $trip) => (float) ($trip->distance_km ?? 0)));
        $sheet->setCellValue('I' . $totalRow, (float) $trips->sum(fn (Trip $trip) => (float) ($trip->price ?? 0)));
        $sheet->getStyle('G' . $totalRow . ':I' . $totalRow)->getFont()->setBold(true);

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
