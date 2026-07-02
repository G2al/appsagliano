<?php

namespace App\Filament\Widgets\Concerns;

use App\Support\FinancialReportPeriod;

trait InteractsWithFinancialReportData
{
    protected function resolveFinancialReportPeriod(): FinancialReportPeriod
    {
        return FinancialReportPeriod::fromFilters($this->filters ?? []);
    }

    protected function formatMoney(mixed $value): string
    {
        return 'EUR ' . number_format((float) ($value ?? 0), 2, ',', '.');
    }

    protected function formatVehicleLabel(object $record): string
    {
        $label = trim(
            (($record->plate ?? null) ? $record->plate . ' - ' : '') .
            ($record->name ?? '')
        );

        return $label !== '' ? $label : 'Veicolo';
    }
}
