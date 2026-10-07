<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Station;
use App\Models\StationCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StationCardController extends Controller
{
    public function store(Request $request, Station $station): JsonResponse
    {
        if (! $station->uses_credit_cards) {
            throw ValidationException::withMessages([
                'number' => ['La stazione selezionata non consente rifornimenti con carta di credito.'],
            ]);
        }

        $validated = $request->validate([
            'number' => ['required', 'string', 'max:255', Rule::unique('station_cards', 'number')],
            'label' => ['nullable', 'string', 'max:255'],
        ], [
            'required' => 'Il campo :attribute e obbligatorio.',
            'number.unique' => 'Esiste gia una carta con questo numero.',
        ], [
            'number' => 'numero carta',
            'label' => 'etichetta',
        ]);

        $card = $station->cards()->create($validated);

        return response()->json($card, 201);
    }
}
