<?php

namespace App\Support;

use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

trait BuildsMonthlyTripSchedule
{
    /**
     * @return array{days: array<int, array{day: int, date: Carbon, trips: Collection<int, Trip>}>, maxTripsPerDay: int, totalTrips: int, filialeTotals: Collection<int, array{filiale: string, total: float}>}
     */
    protected function buildMonthlyScheduleFor(User $owner, ?string $month): array
    {
        $reference = Carbon::createFromFormat('Y-m-d', ($month ?: now()->format('Y-m')) . '-01');
        $start = $reference->copy()->startOfMonth();
        $end = $reference->copy()->endOfMonth();

        /** @var Collection<int, Trip> $trips */
        $trips = $owner->trips()
            ->with(['filiale', 'vehicle'])
            ->whereBetween('date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $byDay = $trips->groupBy(fn (Trip $trip) => $trip->date->format('j'));

        $maxTripsPerDay = max(1, (int) $byDay->map->count()->max());

        $days = [];

        for ($day = 1; $day <= $end->day; $day++) {
            $dayTrips = $byDay->get((string) $day, collect())->values();

            $days[] = [
                'day' => $day,
                'date' => $start->copy()->day($day),
                'trips' => $dayTrips,
                'total' => $dayTrips->sum(fn (Trip $trip) => (float) ($trip->price ?? 0)),
            ];
        }

        $filialeTotals = $trips
            ->groupBy(fn (Trip $trip) => $trip->filiale?->name ?? 'N/D')
            ->map(fn (Collection $filialeTrips, string $filialeName) => [
                'filiale' => $filialeName,
                'total' => $filialeTrips->sum(fn (Trip $trip) => (float) ($trip->price ?? 0)),
            ])
            ->sortBy('filiale')
            ->values();

        return [
            'days' => $days,
            'maxTripsPerDay' => $maxTripsPerDay,
            'totalTrips' => $trips->count(),
            'filialeTotals' => $filialeTotals,
        ];
    }
}
