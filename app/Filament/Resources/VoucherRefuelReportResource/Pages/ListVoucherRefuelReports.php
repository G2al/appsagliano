<?php

namespace App\Filament\Resources\VoucherRefuelReportResource\Pages;

use App\Filament\Resources\VoucherRefuelReportResource;
use App\Filament\Resources\VoucherRefuelReportResource\Widgets\VoucherRefuelStatsWidget;
use Filament\Resources\Pages\ListRecords;

class ListVoucherRefuelReports extends ListRecords
{
    protected static string $resource = VoucherRefuelReportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            VoucherRefuelStatsWidget::class,
        ];
    }
}

