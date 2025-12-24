<?php

namespace App\Filament\Resources\MaintenanceReportResource\Pages;

use App\Filament\Resources\MaintenanceReportResource;
use App\Filament\Widgets\MaintenancesBySupplierTable;
use App\Filament\Widgets\MaintenancesByVehicleTable;
use App\Filament\Widgets\MaintenancesByVehicleSupplierTable;
use App\Filament\Widgets\MaintenancesStats;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceReports extends ListRecords
{
    protected static string $resource = MaintenanceReportResource::class;

    protected function getHeaderActions(): array
    {
        return []; // nessuna creazione/modifica da qui
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MaintenancesStats::class,
            MaintenancesBySupplierTable::class,
            MaintenancesByVehicleTable::class,
            MaintenancesByVehicleSupplierTable::class,
        ];
    }
}
