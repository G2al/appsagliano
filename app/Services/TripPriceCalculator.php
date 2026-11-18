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

    /**
     * Guadagno del proprietario per il viaggio: prezzo owner del listino
     * filiale per quel numero di viaggio, piu' l'eventuale supplemento
     * per scarichi configurato sulla dicitura (goods type) della filiale.
     */
    public function resolveOwnerPrice(Filiale $filiale, int $tripOrder, int $scarichi): ?float
    {
        $base = $filiale->ownerPriceForTripOrder($tripOrder);

        if ($base === null) {
            return null;
        }

        $bonus = $filiale->goodsType?->dischargeBonusForScarichi($scarichi) ?? 0.0;

        return round($base + $bonus, 2);
    }

    /**
     * Somma degli importi extra selezionati (es. extra serale/festivo),
     * da aggiungere al prezzo autista.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\PlatformExtra>  $extras
     */
    public function resolveExtrasAmount($extras): float
    {
        return round((float) $extras->sum(fn ($extra) => (float) $extra->amount), 2);
    }
}
