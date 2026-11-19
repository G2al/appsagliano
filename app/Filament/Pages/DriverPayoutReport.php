<?php

namespace App\Filament\Pages;

use App\Models\DriverMonthlyAdjustment;
use App\Models\DriverMonthlySalary;
use App\Models\User;
use App\Support\DriverPayoutPdfExporter;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

class DriverPayoutReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-currency-euro';
    protected static ?string $navigationLabel = 'Report pagamenti';
    protected static ?string $title = 'Report pagamenti autisti';
    protected static ?string $navigationGroup = 'Movimenti';
    protected static string $view = 'filament.pages.driver-payout-report';

    #[Url]
    public string $month = '';

    public function mount(): void
    {
        if ($this->month === '') {
            $this->month = now()->format('Y-m');
        }
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    /**
     * @return array<int, array{company: string, iban: ?string, name: string, trips_total: float, salary: float, adjustments_total: float, total: float}>
     */
    public function getRowsProperty(): array
    {
        [$start, $end] = $this->monthRange();

        $salaries = DriverMonthlySalary::query()->where('month', $this->month)->get()->keyBy('user_id');
        $adjustmentsTotals = DriverMonthlyAdjustment::query()
            ->where('month', $this->month)
            ->selectRaw('user_id, SUM(amount) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        return User::query()
            ->where('role', 'worker')
            ->withSum(['trips as price_total' => fn ($query) => $query
                ->whereBetween('date', [$start, $end])
                ->whereNotNull('price')], 'price')
            ->get()
            ->map(function (User $user) use ($salaries, $adjustmentsTotals) {
                $tripsTotal = (float) ($user->price_total ?? 0);
                $salary = (float) ($salaries->get($user->id)?->amount ?? $user->default_salary ?? 0);
                $adjustmentsTotal = (float) ($adjustmentsTotals->get($user->id) ?? 0);

                return [
                    'company' => $user->company ?? '',
                    'iban' => $user->iban,
                    'name' => $user->full_name,
                    'trips_total' => $tripsTotal,
                    'salary' => $salary,
                    'adjustments_total' => $adjustmentsTotal,
                    'total' => round($tripsTotal + $salary + $adjustmentsTotal, 2),
                ];
            })
            ->filter(fn (array $row) => $row['total'] > 0)
            ->sortBy('name')
            ->values()
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_pdf')
                ->label('Scarica PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $rows = collect($this->getRowsProperty());

                    $pdfContent = DriverPayoutPdfExporter::build($this->month, $rows);

                    return response()->streamDownload(
                        fn () => print($pdfContent),
                        "report-pagamenti-{$this->month}.pdf",
                        ['Content-Type' => 'application/pdf']
                    );
                }),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function monthRange(): array
    {
        $reference = Carbon::createFromFormat('Y-m-d', $this->month . '-01');

        return [$reference->copy()->startOfMonth()->startOfDay(), $reference->copy()->endOfMonth()->endOfDay()];
    }
}
