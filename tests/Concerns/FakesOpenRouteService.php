<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Http;

trait FakesOpenRouteService
{
    /**
     * Evita chiamate di rete reali verso OpenRouteService nei test che
     * creano/modificano viaggi: simula un calcolo km riuscito.
     * Chiamare una sola volta per test (Http::fake() non va richiamato di
     * nuovo con pattern sovrapposti: vince sempre il primo stub registrato).
     */
    protected function fakeOpenRouteServiceSuccess(): void
    {
        Http::fake([
            'api.heigit.org/pelias/*' => Http::response([
                'features' => [[
                    'geometry' => ['coordinates' => [12.4964, 41.9028]],
                    'properties' => ['confidence' => 1],
                ]],
            ], 200),
            'api.heigit.org/openrouteservice/*' => Http::response([
                'routes' => [['summary' => ['distance' => 123456.0, 'duration' => 3600]]],
            ], 200),
        ]);
    }
}
