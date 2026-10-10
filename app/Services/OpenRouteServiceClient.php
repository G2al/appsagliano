<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouteServiceClient
{
    private const TIMEOUT_SECONDS = 10;

    /**
     * Geocodifica un indirizzo/citta' in coordinate GPS tramite Pelias.
     *
     * @return array{lat: float, lon: float, confidence: float}|null
     */
    public function geocode(string $query): ?array
    {
        $apiKey = config('services.openrouteservice.key');

        if (! $apiKey || trim($query) === '') {
            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['Authorization' => $apiKey])
                ->get(config('services.openrouteservice.geocode_url'), [
                    'text' => $query,
                    'size' => 1,
                ]);
        } catch (\Throwable $e) {
            Log::warning('ORS geocode request failed', ['query' => $query, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('ORS geocode non-success response', ['query' => $query, 'status' => $response->status()]);

            return null;
        }

        $feature = $response->json('features.0');

        if (! $feature) {
            return null;
        }

        $coordinates = $feature['geometry']['coordinates'] ?? null;

        if (! is_array($coordinates) || count($coordinates) < 2) {
            return null;
        }

        return [
            'lon' => (float) $coordinates[0],
            'lat' => (float) $coordinates[1],
            'confidence' => (float) ($feature['properties']['confidence'] ?? 0),
        ];
    }

    /**
     * Calcola la distanza stradale (metri) per mezzi pesanti lungo una sequenza di coordinate.
     *
     * @param  array<int, array{0: float, 1: float}>  $coordinates  coppie [lon, lat], almeno 2
     */
    public function routeDistanceMeters(array $coordinates): ?float
    {
        $apiKey = config('services.openrouteservice.key');

        if (! $apiKey || count($coordinates) < 2) {
            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'Authorization' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post(config('services.openrouteservice.directions_url'), [
                    'coordinates' => $coordinates,
                ]);
        } catch (\Throwable $e) {
            Log::warning('ORS directions request failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('ORS directions non-success response', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        $distance = $response->json('routes.0.summary.distance');

        return is_numeric($distance) ? (float) $distance : null;
    }
}
