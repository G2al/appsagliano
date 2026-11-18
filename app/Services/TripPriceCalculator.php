<?php

namespace App\Services;

use App\Models\Filiale;
use App\Models\PriceRule;
use App\Models\Trip;
use App\Models\Vehicle;
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

    /**
     * Posizione di questo viaggio nella giornata DEL VEICOLO (indipendente
     * dal numero di viaggio dell'autista): serve per far scattare le
     * regole prezzo legate al veicolo.
     */
    public function resolveVehicleDayPosition(int $vehicleId, Carbon $date, ?int $excludeTripId = null): int
    {
        $count = Trip::query()
            ->where('vehicle_id', $vehicleId)
            ->whereDate('date', $date->toDateString())
            ->when($excludeTripId, fn ($query) => $query->where('id', '!=', $excludeTripId))
            ->count();

        return $count + 1;
    }

    /**
     * Sequenza (in ordine cronologico) delle diciture (goods_type_id) gia'
     * fatte dal veicolo in quella giornata, esclusa l'eventuale tappa che
     * si sta modificando. Serve per confrontarla con la sequenza di una
     * regola prezzo.
     *
     * @return array<int, int>
     */
    public function resolveVehicleDaySequence(int $vehicleId, Carbon $date, ?int $excludeTripId = null): array
    {
        return Trip::query()
            ->where('vehicle_id', $vehicleId)
            ->whereDate('date', $date->toDateString())
            ->when($excludeTripId, fn ($query) => $query->where('id', '!=', $excludeTripId))
            ->with('filiale')
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map(fn (Trip $trip) => $trip->filiale?->goods_type_id)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Se una regola prezzo (tutti i veicoli, o solo quelli con gli stessi
     * bancali) matcha la sequenza di diciture della giornata fino a
     * questa posizione, ne restituisce i prezzi fissi (autista + guadagno);
     * altrimenti null, nel qual caso si usa il listino filiale normale.
     *
     * @param  array<int, int>  $priorSequence  sequenza diciture GIA' fatte dal veicolo quel giorno (senza questo viaggio)
     * @return array{price: float, owner_price: float|null}|null
     */
    public function resolveRuleOverride(Vehicle $vehicle, Filiale $filiale, int $vehicleDayPosition, array $priorSequence): ?array
    {
        if ($filiale->goods_type_id === null) {
            return null;
        }

        $sequence = $priorSequence;
        $sequence[] = $filiale->goods_type_id;

        $rules = PriceRule::query()->with('steps')->get()->filter(fn (PriceRule $rule) => $rule->appliesToVehicle($vehicle));

        foreach ($rules as $rule) {
            if (! $rule->matchesAtPosition($sequence, $vehicleDayPosition)) {
                continue;
            }

            $step = $rule->steps->firstWhere('position', $vehicleDayPosition);

            if (! $step) {
                continue;
            }

            return [
                'price' => (float) $step->price,
                'owner_price' => $step->owner_price !== null ? (float) $step->owner_price : null,
            ];
        }

        return null;
    }
}
