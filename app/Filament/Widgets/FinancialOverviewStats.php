<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithFinancialReportData;
use App\Models\ExtraCost;
use App\Models\Maintenance;
use App\Models\Movement;
use App\Models\TollRoadExpense;
use App\Models\UserSalary;
use App\Models\VehicleRevenue;
use App\Support\FinancialReport;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class FinancialOverviewStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    use InteractsWithFinancialReportData;

    protected static bool $isLazy = false;
    protected static string $view = 'filament.widgets.financial-overview-stats';

    protected int|string|array $columnSpan = 'full';

    public ?string $activeBreakdown = null;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $summary = FinancialReport::summary($this->resolveFinancialReportPeriod());

        return [
            $this->makeClickableStat('revenues_ex_vat', 'Entrate veicoli nette', $this->formatMoney($summary['revenues_ex_vat_total']))
                ->description(number_format($summary['revenue_records_count'], 0, ',', '.') . ' registrazioni')
                ->icon('heroicon-o-banknotes'),
            $this->makeClickableStat('revenues_inc_vat', 'Entrate veicoli con IVA', $this->formatMoney($summary['revenues_inc_vat_total']))
                ->description('Calcolo storico con IVA salvata')
                ->icon('heroicon-o-calculator'),
            $this->makeClickableStat('refuels', 'Costi rifornimenti', $this->formatMoney($summary['refuels_total']))
                ->description(number_format($summary['refuels_count'], 0, ',', '.') . ' movimenti')
                ->icon('heroicon-o-truck'),
            $this->makeClickableStat('maintenances', 'Costi manutenzioni', $this->formatMoney($summary['maintenances_total']))
                ->description(number_format($summary['maintenances_count'], 0, ',', '.') . ' interventi')
                ->icon('heroicon-o-wrench-screwdriver'),
            $this->makeClickableStat('salaries', 'Costi stipendi', $this->formatMoney($summary['salaries_total']))
                ->description(number_format($summary['salary_records_count'], 0, ',', '.') . ' registrazioni')
                ->icon('heroicon-o-users'),
            $this->makeClickableStat('tolls', 'Costi autostrade', $this->formatMoney($summary['tolls_total']))
                ->description(number_format($summary['toll_records_count'], 0, ',', '.') . ' registrazioni')
                ->icon('heroicon-o-map'),
            $this->makeClickableStat('extra_costs', 'Costi extra', $this->formatMoney($summary['extra_costs_total']))
                ->description(number_format($summary['extra_cost_records_count'], 0, ',', '.') . ' registrazioni')
                ->icon('heroicon-o-receipt-percent'),
            $this->makeClickableStat('vehicle_margin', 'Margine veicoli', $this->formatMoney($summary['vehicle_margin_total']))
                ->description('Entrate con IVA - rifornimenti - manutenzioni')
                ->icon('heroicon-o-chart-bar'),
            $this->makeClickableStat('net_margin', 'Utile finale', $this->formatMoney($summary['net_margin_total']))
                ->description('Margine veicoli - stipendi - autostrade - costi extra')
                ->color($summary['net_margin_total'] >= 0 ? 'success' : 'danger')
                ->icon('heroicon-o-presentation-chart-line'),
        ];
    }

    public function openBreakdown(string $key): void
    {
        $this->activeBreakdown = $key;

        $this->dispatch('open-modal', id: $this->getBreakdownModalId());
    }

    public function getBreakdownModalId(): string
    {
        return $this->getId() . '-financial-breakdown';
    }

    public function getActiveBreakdownHeading(): string
    {
        return $this->getActiveBreakdownConfig()['heading'] ?? 'Dettaglio';
    }

    public function getActiveBreakdownColumns(): array
    {
        return $this->getActiveBreakdownConfig()['columns'] ?? [];
    }

    public function getActiveBreakdownRows(): array
    {
        return $this->getActiveBreakdownConfig()['rows'] ?? [];
    }

    public function getActiveBreakdownEmptyMessage(): string
    {
        return $this->getActiveBreakdownConfig()['empty'] ?? 'Nessun dato disponibile.';
    }

    private function getActiveBreakdownConfig(): array
    {
        return $this->getBreakdownConfig($this->activeBreakdown);
    }

    private function getBreakdownConfig(?string $key): array
    {
        $period = $this->resolveFinancialReportPeriod();
        $summary = FinancialReport::summary($period);

        return match ($key) {
            'revenues_ex_vat' => [
                'heading' => 'Dettaglio entrate veicoli nette',
                'columns' => [
                    ['key' => 'date', 'label' => 'Data'],
                    ['key' => 'vehicle', 'label' => 'Veicolo'],
                    ['key' => 'name', 'label' => 'Nome'],
                    ['key' => 'amount_ex_vat', 'label' => 'Netto IVA'],
                    ['key' => 'amount_inc_vat', 'label' => 'Con IVA'],
                ],
                'rows' => VehicleRevenue::query()
                    ->with('vehicle')
                    ->tap(fn ($query) => $period->applyToBuilder($query, 'date'))
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->get()
                    ->map(fn (VehicleRevenue $revenue): array => [
                        'date' => $this->formatDate($revenue->date),
                        'vehicle' => $revenue->vehicle ? $this->formatVehicleLabel($revenue->vehicle) : 'N/D',
                        'name' => $revenue->name ?: '-',
                        'amount_ex_vat' => $this->formatMoney($revenue->amount_ex_vat),
                        'amount_inc_vat' => $this->formatMoney($revenue->amount_inc_vat),
                    ])
                    ->all(),
                'empty' => 'Nessuna entrata veicolo nel periodo selezionato.',
            ],
            'revenues_inc_vat' => [
                'heading' => 'Dettaglio entrate veicoli con IVA',
                'columns' => [
                    ['key' => 'date', 'label' => 'Data'],
                    ['key' => 'vehicle', 'label' => 'Veicolo'],
                    ['key' => 'name', 'label' => 'Nome'],
                    ['key' => 'vat_percentage', 'label' => 'IVA'],
                    ['key' => 'amount_inc_vat', 'label' => 'Con IVA'],
                ],
                'rows' => VehicleRevenue::query()
                    ->with('vehicle')
                    ->tap(fn ($query) => $period->applyToBuilder($query, 'date'))
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->get()
                    ->map(fn (VehicleRevenue $revenue): array => [
                        'date' => $this->formatDate($revenue->date),
                        'vehicle' => $revenue->vehicle ? $this->formatVehicleLabel($revenue->vehicle) : 'N/D',
                        'name' => $revenue->name ?: '-',
                        'vat_percentage' => number_format((float) $revenue->vat_percentage, 2, ',', '.') . '%',
                        'amount_inc_vat' => $this->formatMoney($revenue->amount_inc_vat),
                    ])
                    ->all(),
                'empty' => 'Nessuna entrata veicolo nel periodo selezionato.',
            ],
            'refuels' => [
                'heading' => 'Dettaglio costi rifornimenti',
                'columns' => [
                    ['key' => 'date', 'label' => 'Data'],
                    ['key' => 'vehicle', 'label' => 'Veicolo'],
                    ['key' => 'station', 'label' => 'Stazione'],
                    ['key' => 'author', 'label' => 'Autore'],
                    ['key' => 'amount', 'label' => 'Importo'],
                ],
                'rows' => Movement::query()
                    ->with(['vehicle', 'station', 'user'])
                    ->tap(fn ($query) => $period->applyToBuilder($query, 'date'))
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->get()
                    ->map(fn (Movement $movement): array => [
                        'date' => $this->formatDate($movement->date, true),
                        'vehicle' => $movement->vehicle ? $this->formatVehicleLabel($movement->vehicle) : 'N/D',
                        'station' => $movement->station?->name ?? 'N/D',
                        'author' => $movement->user?->full_name ?: 'N/D',
                        'amount' => $this->formatMoney($movement->price),
                    ])
                    ->all(),
                'empty' => 'Nessun rifornimento nel periodo selezionato.',
            ],
            'maintenances' => [
                'heading' => 'Dettaglio costi manutenzioni',
                'columns' => [
                    ['key' => 'date', 'label' => 'Data'],
                    ['key' => 'vehicle', 'label' => 'Veicolo'],
                    ['key' => 'supplier', 'label' => 'Fornitore'],
                    ['key' => 'author', 'label' => 'Autore'],
                    ['key' => 'amount', 'label' => 'Importo'],
                ],
                'rows' => Maintenance::query()
                    ->with(['vehicle', 'supplier', 'user'])
                    ->tap(fn ($query) => $period->applyToBuilder($query, 'date'))
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->get()
                    ->map(fn (Maintenance $maintenance): array => [
                        'date' => $this->formatDate($maintenance->date, true),
                        'vehicle' => $maintenance->vehicle ? $this->formatVehicleLabel($maintenance->vehicle) : 'N/D',
                        'supplier' => $maintenance->supplier?->name ?? 'N/D',
                        'author' => $maintenance->user?->full_name ?: 'N/D',
                        'amount' => $this->formatMoney($maintenance->price),
                    ])
                    ->all(),
                'empty' => 'Nessuna manutenzione nel periodo selezionato.',
            ],
            'salaries' => [
                'heading' => 'Dettaglio costi stipendi',
                'columns' => [
                    ['key' => 'date', 'label' => 'Data'],
                    ['key' => 'user', 'label' => 'Utente'],
                    ['key' => 'amount', 'label' => 'Importo'],
                ],
                'rows' => UserSalary::query()
                    ->with('user')
                    ->tap(fn ($query) => $period->applyToBuilder($query, 'date'))
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->get()
                    ->map(fn (UserSalary $salary): array => [
                        'date' => $this->formatDate($salary->date),
                        'user' => $salary->user?->full_name ?: 'N/D',
                        'amount' => $this->formatMoney($salary->amount),
                    ])
                    ->all(),
                'empty' => 'Nessuno stipendio nel periodo selezionato.',
            ],
            'tolls' => [
                'heading' => 'Dettaglio costi autostrade',
                'columns' => [
                    ['key' => 'date', 'label' => 'Data'],
                    ['key' => 'name', 'label' => 'Autostrada'],
                    ['key' => 'amount', 'label' => 'Importo'],
                ],
                'rows' => TollRoadExpense::query()
                    ->with('tollRoad')
                    ->tap(fn ($query) => $period->applyToBuilder($query, 'date'))
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->get()
                    ->map(fn (TollRoadExpense $expense): array => [
                        'date' => $this->formatDate($expense->date),
                        'name' => $expense->tollRoad?->name ?? 'N/D',
                        'amount' => $this->formatMoney($expense->amount),
                    ])
                    ->all(),
                'empty' => 'Nessun costo autostrada nel periodo selezionato.',
            ],
            'extra_costs' => [
                'heading' => 'Dettaglio costi extra',
                'columns' => [
                    ['key' => 'date', 'label' => 'Data'],
                    ['key' => 'description', 'label' => 'Dicitura'],
                    ['key' => 'amount', 'label' => 'Importo'],
                ],
                'rows' => ExtraCost::query()
                    ->tap(fn ($query) => $period->applyToBuilder($query, 'date'))
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->get()
                    ->map(fn (ExtraCost $cost): array => [
                        'date' => $this->formatDate($cost->date),
                        'description' => $cost->description,
                        'amount' => $this->formatMoney($cost->amount),
                    ])
                    ->all(),
                'empty' => 'Nessun costo extra nel periodo selezionato.',
            ],
            'vehicle_margin' => [
                'heading' => 'Dettaglio margine veicoli',
                'columns' => [
                    ['key' => 'vehicle', 'label' => 'Veicolo'],
                    ['key' => 'revenues', 'label' => 'Entrate con IVA'],
                    ['key' => 'refuels', 'label' => 'Rifornimenti'],
                    ['key' => 'maintenances', 'label' => 'Manutenzioni'],
                    ['key' => 'margin', 'label' => 'Margine'],
                ],
                'rows' => FinancialReport::vehiclePerformanceQuery($period)
                    ->orderByDesc('operating_margin')
                    ->get()
                    ->map(fn ($vehicle): array => [
                        'vehicle' => $this->formatVehicleLabel($vehicle),
                        'revenues' => $this->formatMoney($vehicle->revenues_inc_vat_total),
                        'refuels' => $this->formatMoney($vehicle->refuels_total),
                        'maintenances' => $this->formatMoney($vehicle->maintenances_total),
                        'margin' => $this->formatMoney($vehicle->operating_margin),
                    ])
                    ->all(),
                'empty' => 'Nessun veicolo con dati economici nel periodo selezionato.',
            ],
            'net_margin' => [
                'heading' => 'Dettaglio utile finale',
                'columns' => [
                    ['key' => 'label', 'label' => 'Voce'],
                    ['key' => 'amount', 'label' => 'Importo'],
                ],
                'rows' => [
                    ['label' => 'Entrate veicoli con IVA', 'amount' => $this->formatMoney($summary['revenues_inc_vat_total'])],
                    ['label' => 'Costi rifornimenti', 'amount' => $this->formatMoney($summary['refuels_total'])],
                    ['label' => 'Costi manutenzioni', 'amount' => $this->formatMoney($summary['maintenances_total'])],
                    ['label' => 'Costi stipendi', 'amount' => $this->formatMoney($summary['salaries_total'])],
                    ['label' => 'Costi autostrade', 'amount' => $this->formatMoney($summary['tolls_total'])],
                    ['label' => 'Costi extra', 'amount' => $this->formatMoney($summary['extra_costs_total'])],
                    ['label' => 'Utile finale', 'amount' => $this->formatMoney($summary['net_margin_total'])],
                ],
                'empty' => 'Nessun dato disponibile.',
            ],
            default => [
                'heading' => 'Dettaglio',
                'columns' => [],
                'rows' => [],
                'empty' => 'Nessun dato disponibile.',
            ],
        };
    }

    private function makeClickableStat(string $key, string $label, string $value): Stat
    {
        return Stat::make($label, $value)
            ->extraAttributes([
                'class' => 'cursor-pointer transition hover:ring-2 hover:ring-primary-500/40 focus:outline-none',
                'wire:click' => "openBreakdown('{$key}')",
            ]);
    }

    private function formatDate(mixed $value, bool $withTime = false): string
    {
        if (! $value) {
            return '-';
        }

        $date = $value instanceof Carbon
            ? $value
            : Carbon::parse($value);

        return $date->format($withTime ? 'd/m/Y H:i' : 'd/m/Y');
    }
}
