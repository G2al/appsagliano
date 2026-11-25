<?php

namespace App\Support;

use App\Models\Maintenance;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MaintenanceExcelExporter
{
    private const HEADERS = [
        'Data', 'Veicolo', 'Fornitore', 'Km attuali', 'Prossima scadenza',
        'Fattura', 'Note', 'Prezzo (€)',
    ];

    /**
     * @param  Collection<int, Maintenance>  $maintenances
     */
    public static function build(string $title, Collection $maintenances): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($title, 0, 31) ?: 'Report');

        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->getStyle('A1:' . self::lastColumn() . '1')->getFont()->setBold(true);

        $row = 2;

        foreach ($maintenances as $maintenance) {
            $sheet->fromArray([
                $maintenance->date?->format('d/m/Y H:i'),
                trim(($maintenance->vehicle?->plate ? $maintenance->vehicle->plate . ' - ' : '') . ($maintenance->vehicle?->name ?? '')),
                $maintenance->supplier?->name,
                $maintenance->km_current,
                $maintenance->next_maintenance_date?->format('d/m/Y'),
                $maintenance->invoice_number,
                $maintenance->notes,
                $maintenance->price !== null ? (float) $maintenance->price : null,
            ], null, 'A' . $row);

            $row++;
        }

        $totalRow = $row;
        $sheet->setCellValue('G' . $totalRow, 'Totale');
        $sheet->setCellValue('H' . $totalRow, (float) $maintenances->sum(fn (Maintenance $m) => (float) ($m->price ?? 0)));
        $sheet->getStyle('G' . $totalRow . ':H' . $totalRow)->getFont()->setBold(true);

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
