<?php

namespace App\Services;

use App\Models\Filiale;
use App\Models\Trip;
use Illuminate\Support\Carbon;

class TripPriceCalculator
{
    /**
     * Quanti bordero ha gia' fatto l'autista nello stesso giorno (prima di
     * questo), +1: il "numero viaggio" usato per pescare il prezzo dal
     * listino della filiale. Esclude il record stesso quando si modifica
     * un viaggio gia' esistente.
     */
    public function resolveTripOrder(int $userId, Carbon $date, ?int $excludeTripId = null): int
    {
        $count = Trip::query()
            ->where('user_id', $userId)
            ->whereDate('date', $date->toDateString())
            ->when($excludeTripId, fn ($query) => $query->where('id', '!=', $excludeTripId))
            ->count();

        return $count + 1;
    }

    /**
     * Prezzo dal listino della filiale per quel numero di viaggio, o null
     * se la filiale non ha un prezzo impostato per quell'ordine (va
     * inserito a mano dall'admin).
     */
    public function resolvePrice(Filiale $filiale, int $tripOrder): ?float
    {
        return $filiale->priceForTripOrder($tripOrder);
    }
}
