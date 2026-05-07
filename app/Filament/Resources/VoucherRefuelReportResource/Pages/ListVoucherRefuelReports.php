<?php

namespace App\Filament\Resources\VoucherRefuelReportResource\Pages;

use App\Filament\Resources\VoucherRefuelReportResource;
use App\Filament\Resources\VoucherRefuelReportResource\Widgets\VoucherRefuelStatsWidget;
use App\Filament\Widgets\Concerns\InteractsWithReportTableChecks;
use App\Models\Movement;
use Carbon\Carbon;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ListVoucherRefuelReports extends ListRecords
{
    use InteractsWithReportTableChecks;

    protected static string $resource = VoucherRefuelReportResource::class;

    public function table(Table $table): Table
    {
        $table = parent::table($table);

        return $table->columns([
            $this->getReportTableCheckColumn(),
            ...$table->getColumnsLayout(),
        ]);
    }

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

    protected function getReportTableRowKey(Model $record): string
    {
        /** @var Movement $record */
        return 'movement:' . $record->getKey();
    }

    protected function getReportTableCheckDateRange(): array
    {
        $filters = $this->tableFilters ?? [];
        $dateRange = $filters['date_range'] ?? [];

        $startRaw = $dateRange['start'] ?? null;
        $endRaw = $dateRange['end'] ?? null;

        $start = Carbon::parse($startRaw ?: now()->startOfMonth())->startOfDay();
        $end = Carbon::parse($endRaw ?: now())->endOfDay();

        return [$start, $end];
    }
}
