<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\Trip;
use Carbon\Carbon;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class TripsMonthlyRelationManager extends RelationManager
{
    protected static string $relationship = 'trips';

    protected static ?string $title = 'Scheda viaggi mensile';

    protected static string $view = 'filament.relation-managers.trips-monthly';

    public ?string $month = null;

    public function mount(): void
    {
        parent::mount();

        $this->month = now()->format('Y-m');
    }

    public function table(Table $table): Table
    {
        return $table->columns([]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $reference = Carbon::createFromFormat('Y-m-d', ($this->month ?: now()->format('Y-m')) . '-01');
        $start = $reference->copy()->startOfMonth();
        $end = $reference->copy()->endOfMonth();

        /** @var Collection<int, Trip> $trips */
        $trips = $this->ownerRecord->trips()
            ->with(['platform', 'vehicle'])
            ->whereBetween('date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $byDay = $trips->groupBy(fn (Trip $trip) => $trip->date->format('j'));

        $maxTripsPerDay = max(1, (int) $byDay->map->count()->max());

        $days = [];

        for ($day = 1; $day <= $end->day; $day++) {
            $days[] = [
                'day' => $day,
                'date' => $start->copy()->day($day),
                'trips' => $byDay->get((string) $day, collect())->values(),
            ];
        }

        return [
            'days' => $days,
            'maxTripsPerDay' => $maxTripsPerDay,
            'totalTrips' => $trips->count(),
        ];
    }
}
