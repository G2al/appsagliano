<?php

namespace App\Support;

use App\Models\Movement;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MovementExcelExporter
{
    private const HEADERS = [
        'Data', 'Veicolo', 'Stazione', 'Km iniziali', 'Km finali', 'Litri',
        'Km/litro', 'Pagamento', 'Prezzo (€)',
    ];

    /**
     * @param  Collection<int, Movement>  $movements
     */
    public static function build(string $title, Collection $movements): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($title, 0, 31) ?: 'Report');

        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->getStyle('A1:' . self::lastColumn() . '1')->getFont()->setBold(true);

        $row = 2;

        foreach ($movements as $movement) {
            $sheet->fromArray([
                $movement->date?->format('d/m/Y H:i'),
                trim(($movement->vehicle?->plate ? $movement->vehicle->plate . ' - ' : '') . ($movement->vehicle?->name ?? '')),
                $movement->station?->name,
                $movement->km_start,
                $movement->km_end,
                $movement->liters !== null ? (float) $movement->liters : null,
                $movement->km_per_liter !== null ? (float) $movement->km_per_liter : null,
                $movement->is_voucher ? 'Buono' : 'Credito',
                $movement->price !== null ? (float) $movement->price : null,
            ], null, 'A' . $row);

            $row++;
        }

        $totalRow = $row;
        $sheet->setCellValue('F' . $totalRow, 'Totale');
        $sheet->setCellValue('G' . $totalRow, (float) $movements->avg(fn (Movement $m) => (float) ($m->km_per_liter ?? 0)));
        $sheet->setCellValue('I' . $totalRow, (float) $movements->sum(fn (Movement $m) => (float) ($m->price ?? 0)));
        $sheet->getStyle('F' . $totalRow . ':I' . $totalRow)->getFont()->setBold(true);

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
