<?php

namespace App\Services;

use App\Models\Platform;
use App\Models\PlatformAddress;
use App\Models\Trip;
use Illuminate\Support\Facades\Cache;

class TripDistanceCalculator
{
    /**
     * Sotto questa soglia di confidenza del geocoding (0-1) il risultato
     * viene considerato una stima da verificare a mano, non un punto preciso.
     */
    private const CONFIDENCE_THRESHOLD = 0.8;

    public const ERROR_PLATFORM_MISSING_ADDRESS = 'platform_missing_address';

    public const ERROR_PLATFORM_ADDRESS_REQUIRED = 'platform_address_required';

    public const ERROR_PLATFORM_GEOCODING_FAILED = 'platform_geocoding_failed';

    public const ERROR_DESTINATION_NOT_FOUND = 'destination_not_found';

    public const ERROR_SERVICE_UNAVAILABLE = 'service_unavailable';

    public const ERROR_UNKNOWN = 'unknown';

    public function __construct(private OpenRouteServiceClient $client)
    {
    }

    /**
     * Calcola e salva i km stradali del viaggio: indirizzo piattaforma ->
     * destinazioni (in ordine) -> indirizzo piattaforma.
     * Non lancia mai eccezioni: in caso di problemi marca il viaggio come "non disponibile".
     */
    public function calculate(Trip $trip): void
    {
        $platform = $trip->platform ?? $trip->platform()->first();
        $platformAddress = $this->resolvePlatformAddressForTrip($trip, $platform);

        $result = $platformAddress instanceof PlatformAddress
            ? $this->compute($platformAddress, $trip->destinations ?? [])
            : $platformAddress; // gia' un risultato "unavailable"

        $trip->forceFill([
            'distance_km' => $result['distance_km'],
            'distance_status' => $result['distance_status'],
            'distance_note' => $result['distance_note'],
            'distance_error_code' => $result['distance_error_code'],
            'distance_error_destination' => $result['distance_error_destination'],
            'distance_calculated_at' => now(),
        ])->saveQuietly();
    }

    /**
     * Calcola i km stradali senza salvare nulla sul viaggio: usato per le
     * anteprime nel form, prima che il viaggio esista o venga modificato.
     * Se la piattaforma ha piu' di un indirizzo, $platformAddressId e'
     * obbligatorio per sapere quale usare.
     *
     * @param  array<int, string>  $destinations
     * @return array{distance_km: float|null, distance_status: string, distance_note: string|null, distance_error_code: string|null, distance_error_destination: string|null}
     */
    public function preview(?Platform $platform, array $destinations, ?int $platformAddressId = null): array
    {
        $platformAddress = $this->resolvePlatformAddress($platform, $platformAddressId);

        if (! $platformAddress instanceof PlatformAddress) {
            return $platformAddress;
        }

        return $this->compute($platformAddress, $destinations);
    }

    /**
     * @return PlatformAddress|array{distance_km: null, distance_status: 'unavailable', distance_note: string, distance_error_code: string, distance_error_destination: null}
     */
    private function resolvePlatformAddressForTrip(Trip $trip, ?Platform $platform): PlatformAddress|array
    {
        if ($trip->platform_address_id) {
            $address = PlatformAddress::query()->find($trip->platform_address_id);

            if ($address) {
                return $address;
            }
        }

        return $this->resolvePlatformAddress($platform, null);
    }

    /**
     * @return PlatformAddress|array{distance_km: null, distance_status: 'unavailable', distance_note: string, distance_error_code: string, distance_error_destination: null}
     */
    private function resolvePlatformAddress(?Platform $platform, ?int $platformAddressId): PlatformAddress|array
    {
        if (! $platform) {
            return $this->unavailable(self::ERROR_PLATFORM_MISSING_ADDRESS, 'Piattaforma senza indirizzo registrato.');
        }

        if ($platformAddressId) {
            $address = $platform->addresses->firstWhere('id', $platformAddressId);

            if (! $address) {
                return $this->unavailable(self::ERROR_PLATFORM_MISSING_ADDRESS, 'Indirizzo piattaforma non valido.');
            }

            return $address;
        }

        $addresses = $platform->addresses;

        if ($addresses->isEmpty()) {
            return $this->unavailable(self::ERROR_PLATFORM_MISSING_ADDRESS, 'Piattaforma senza indirizzo registrato.');
        }

        if ($addresses->count() > 1) {
            return $this->unavailable(
                self::ERROR_PLATFORM_ADDRESS_REQUIRED,
                'La piattaforma ha piu indirizzi: specifica quale usare per questo viaggio.'
            );
        }

        return $addresses->first();
    }

    /**
     * @param  array<int, string>  $destinations
     * @return array{distance_km: float|null, distance_status: string, distance_note: string|null, distance_error_code: string|null, distance_error_destination: string|null}
     */
    private function compute(PlatformAddress $platformAddress, array $destinations): array
    {
        $platformCoords = $this->resolvePlatformAddressCoordinates($platformAddress);

        if (! $platformCoords) {
            return $this->unavailable(self::ERROR_PLATFORM_GEOCODING_FAILED, 'Impossibile geolocalizzare la piattaforma.');
        }

        $cleanDestinations = collect($destinations)->filter(fn ($d) => trim((string) $d) !== '')->values();

        if ($cleanDestinations->isEmpty()) {
            return $this->unavailable(self::ERROR_UNKNOWN, 'Nessuna destinazione da calcolare.');
        }

        $waypoints = [$platformCoords];
        $isEstimate = false;

        foreach ($cleanDestinations as $destination) {
            $geo = $this->geocodeCached($destination);

            if (! $geo) {
                return $this->unavailable(
                    self::ERROR_DESTINATION_NOT_FOUND,
                    "Impossibile geolocalizzare la destinazione \"{$destination}\".",
                    $destination
                );
            }

            if ($geo['confidence'] < self::CONFIDENCE_THRESHOLD) {
                $isEstimate = true;
            }

            $waypoints[] = [$geo['lon'], $geo['lat']];
        }

        $waypoints[] = $platformCoords;

        $distanceMeters = $this->client->routeDistanceMeters($waypoints);

        if ($distanceMeters === null) {
            return $this->unavailable(self::ERROR_SERVICE_UNAVAILABLE, 'Servizio di calcolo percorso non disponibile al momento.');
        }

        return [
            'distance_km' => round($distanceMeters / 1000, 2),
            'distance_status' => $isEstimate ? 'estimated' : 'calculated',
            'distance_note' => $isEstimate
                ? 'Stima: una o piu destinazioni sono imprecise, meglio controllare a mano.'
                : null,
            'distance_error_code' => null,
            'distance_error_destination' => null,
        ];
    }

    /**
     * @return array{distance_km: null, distance_status: 'unavailable', distance_note: string, distance_error_code: string, distance_error_destination: string|null}
     */
    private function unavailable(string $errorCode, string $note, ?string $destination = null): array
    {
        return [
            'distance_km' => null,
            'distance_status' => 'unavailable',
            'distance_note' => $note,
            'distance_error_code' => $errorCode,
            'distance_error_destination' => $destination,
        ];
    }

    /**
     * @return array{0: float, 1: float}|null coppia [lon, lat]
     */
    private function resolvePlatformAddressCoordinates(PlatformAddress $platformAddress): ?array
    {
        if ($platformAddress->latitude !== null && $platformAddress->longitude !== null) {
            return [(float) $platformAddress->longitude, (float) $platformAddress->latitude];
        }

        $geo = $this->client->geocode($platformAddress->address);

        if (! $geo) {
            return null;
        }

        $platformAddress->forceFill([
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
