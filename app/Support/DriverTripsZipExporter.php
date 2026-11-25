<?php

namespace App\Support;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;

class DriverTripsZipExporter
{
    private const MONTH_NAMES = [
        1 => '01-Gennaio', 2 => '02-Febbraio', 3 => '03-Marzo', 4 => '04-Aprile',
        5 => '05-Maggio', 6 => '06-Giugno', 7 => '07-Luglio', 8 => '08-Agosto',
        9 => '09-Settembre', 10 => '10-Ottobre', 11 => '11-Novembre', 12 => '12-Dicembre',
    ];

    /**
     * Esporta i viaggi di un singolo autista, un file .xlsx per mese (solo
     * se ci sono viaggi in quel mese), organizzati in Anno/Mese dentro lo zip.
     */
    public static function buildForDriver(User $driver): string
    {
        $trips = Trip::query()
            ->with(['vehicle', 'platform', 'attachments'])
            ->where('user_id', $driver->id)
            ->orderBy('date')
            ->get();

        return self::buildZip([$driver->id => $driver], $trips);
    }

    /**
     * Esporta i viaggi di TUTTI gli autisti, stessa struttura a cartelle,
     * una sottocartella per autista dentro ogni mese.
     */
    public static function buildForAllDrivers(): string
    {
        $drivers = User::query()->where('role', 'worker')->get()->keyBy('id');

        $trips = Trip::query()
            ->with(['vehicle', 'platform', 'attachments', 'user'])
            ->whereIn('user_id', $drivers->keys())
            ->orderBy('date')
            ->get();

        return self::buildZip($drivers->all(), $trips);
    }

    /**
     * @param  array<int, User>  $drivers
     * @param  Collection<int, Trip>  $trips
     */
    private static function buildZip(array $drivers, Collection $trips): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'trips_export_') . '.zip';

        $zip = new ZipArchive();
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $grouped = $trips->groupBy(fn (Trip $trip) => $trip->user_id . '|' . $trip->date->format('Y-m'));

        $tmpXlsxFiles = [];

        foreach ($grouped as $key => $monthlyTrips) {
            [$userId, $yearMonth] = explode('|', $key);
            $driver = $drivers[(int) $userId] ?? null;

            if (! $driver) {
                continue;
            }

            [$year, $month] = explode('-', $yearMonth);
            $monthLabel = self::MONTH_NAMES[(int) $month] ?? $month;
            $driverFolder = self::sanitize($driver->full_name ?: ('autista-' . $driver->id));

            $spreadsheet = TripExcelExporter::build($monthLabel . ' ' . $year, $monthlyTrips);

            $tmpXlsx = tempnam(sys_get_temp_dir(), 'trip_month_') . '.xlsx';
            (new Xlsx($spreadsheet))->save($tmpXlsx);
            $tmpXlsxFiles[] = $tmpXlsx;

            $zip->addFile($tmpXlsx, "{$year}/{$monthLabel}/{$driverFolder}/{$monthLabel}-{$year}.xlsx");
        }

        $zip->close();

        foreach ($tmpXlsxFiles as $tmpXlsx) {
            @unlink($tmpXlsx);
        }

        $content = file_get_contents($tmpPath);
        unlink($tmpPath);

        return $content;
    }

    private static function sanitize(string $value): string
    {
        return trim(preg_replace('/[^A-Za-z0-9\- ]/', '', $value)) ?: 'autista';
    }
}
