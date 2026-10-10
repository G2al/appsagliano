<?php

namespace App\Services;

use App\Models\Platform;
use App\Models\Trip;
use Illuminate\Support\Facades\Cache;

class TripDistanceCalculator
{
    /**
     * Sotto questa soglia di confidenza del geocoding (0-1) il risultato
     * viene considerato una stima da verificare a mano, non un punto preciso.
     */
    private const CONFIDENCE_THRESHOLD = 0.8;

    public function __construct(private OpenRouteServiceClient $client)
    {
    }

    /**
     * Calcola e salva i km stradali del viaggio: Piattaforma -> destinazioni (in ordine) -> Piattaforma.
     * Non lancia mai eccezioni: in caso di problemi marca il viaggio come "non disponibile".
     */
    public function calculate(Trip $trip): void
    {
        $platform = $trip->platform ?? $trip->platform()->first();

        $result = $this->compute($platform, $trip->destinations ?? []);

        $trip->forceFill([
            'distance_km' => $result['distance_km'],
            'distance_status' => $result['distance_status'],
            'distance_note' => $result['distance_note'],
            'distance_calculated_at' => now(),
        ])->saveQuietly();
    }

    /**
     * Calcola i km stradali senza salvare nulla sul viaggio: usato per le
     * anteprime nel form, prima che il viaggio esista o venga modificato.
     * Geocodifica e salva comunque le coordinate della piattaforma se mancanti
     * (cache legittima, non e' uno stato del viaggio).
     *
     * @param  array<int, string>  $destinations
     * @return array{distance_km: float|null, distance_status: string, distance_note: string|null}
     */
    public function preview(?Platform $platform, array $destinations): array
    {
        return $this->compute($platform, $destinations);
    }

    /**
     * @param  array<int, string>  $destinations
     * @return array{distance_km: float|null, distance_status: string, distance_note: string|null}
     */
    private function compute(?Platform $platform, array $destinations): array
    {
        if (! $platform || ! $platform->address) {
            return $this->unavailable('Piattaforma senza indirizzo registrato.');
        }

        $platformCoords = $this->resolvePlatformCoordinates($platform);

        if (! $platformCoords) {
            return $this->unavailable('Impossibile geolocalizzare la piattaforma.');
        }

        $cleanDestinations = collect($destinations)->filter(fn ($d) => trim((string) $d) !== '')->values();

        if ($cleanDestinations->isEmpty()) {
            return $this->unavailable('Nessuna destinazione da calcolare.');
        }

        $waypoints = [$platformCoords];
        $isEstimate = false;

        foreach ($cleanDestinations as $destination) {
            $geo = $this->geocodeCached($destination);

            if (! $geo) {
                return $this->unavailable("Impossibile geolocalizzare la destinazione \"{$destination}\".");
            }

            if ($geo['confidence'] < self::CONFIDENCE_THRESHOLD) {
                $isEstimate = true;
            }

            $waypoints[] = [$geo['lon'], $geo['lat']];
        }

        $waypoints[] = $platformCoords;

        $distanceMeters = $this->client->routeDistanceMeters($waypoints);

        if ($distanceMeters === null) {
            return $this->unavailable('Servizio di calcolo percorso non disponibile al momento.');
        }

        return [
            'distance_km' => round($distanceMeters / 1000, 2),
            'distance_status' => $isEstimate ? 'estimated' : 'calculated',
            'distance_note' => $isEstimate
                ? 'Stima: una o piu destinazioni sono imprecise, meglio controllare a mano.'
                : null,
        ];
    }

    /**
     * @return array{distance_km: null, distance_status: 'unavailable', distance_note: string}
     */
    private function unavailable(string $note): array
    {
        return [
            'distance_km' => null,
            'distance_status' => 'unavailable',
            'distance_note' => $note,
        ];
    }

    /**
     * @return array{0: float, 1: float}|null coppia [lon, lat]
     */
    private function resolvePlatformCoordinates(Platform $platform): ?array
    {
        if ($platform->latitude !== null && $platform->longitude !== null) {
            return [(float) $platform->longitude, (float) $platform->latitude];
        }

        $geo = $this->client->geocode($platform->address);

        if (! $geo) {
            return null;
        }

        $platform->forceFill([
            'latitude' => $geo['lat'],
            'longitude' => $geo['lon'],
        ])->saveQuietly();

        return [$geo['lon'], $geo['lat']];
    }

    /**
     * @return array{lat: float, lon: float, confidence: float}|null
     */
    private function geocodeCached(string $query): ?array
    {
        $key = 'ors:geocode:' . md5(mb_strtolower(trim($query)));

        return Cache::remember($key, now()->addDays(30), fn () => $this->client->geocode($query));
    }
}
