<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movement;
use App\Models\Station;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Movement::with(['station', 'vehicle', 'user', 'updatedBy'])
            ->when($user->role !== 'admin', fn ($q) => $q->where('user_id', $user->id))
            ->latest();

        return response()->json($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->role, ['worker', 'admin'], true)) {
            throw ValidationException::withMessages([
                'role' => ['Ruolo non autorizzato a creare movimenti.'],
            ]);
        }

        $validated = $request->validate([
            'station_id' => ['required', Rule::exists('stations', 'id')],
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')],
            'date' => ['required', 'date'],
            'km_start' => ['required', 'integer', 'min:0'],
            'km_end' => ['required', 'integer', 'min:0'],
            'liters' => ['required', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'adblue' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'photo' => ['required', 'image', 'max:5120'],
        ], [
            'required' => 'Il campo :attribute è obbligatorio.',
            'photo.image' => 'Carica un file immagine valido.',
            'photo.max' => 'La foto non può superare 5MB.',
        ], [
            'station_id' => 'stazione',
            'vehicle_id' => 'veicolo',
            'date' => 'data',
            'km_start' => 'km iniziali',
            'km_end' => 'km finali',
            'liters' => 'litri',
            'price' => 'prezzo',
            'photo' => 'ricevuta',
        ]);

        $photoPath = null;

        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('receipts', 'public');
        }

        $station = Station::select('id', 'credit_balance')->find($validated['station_id']);
        $stationCharge = ($station && $station->credit_balance !== null)
            ? (float) $validated['price']
            : 0.0;

        if ($stationCharge > 0) {
            $balance = (float) $station->credit_balance;
            if ($balance < $stationCharge) {
                throw ValidationException::withMessages([
                    'price' => ['Credito insufficiente sulla stazione selezionata.'],
                ]);
            }
        }

        $movement = DB::transaction(function () use ($validated, $photoPath, $stationCharge, $user) {
            $movement = Movement::create([
                ...$validated,
                'photo_path' => $photoPath,
                'user_id' => $user->id,
                'station_charge' => $stationCharge,
            ]);

            if ($stationCharge > 0 && ! empty($validated['station_id'])) {
                Station::whereKey($validated['station_id'])->decrement('credit_balance', $stationCharge);
            }

            if (! empty($validated['vehicle_id']) && isset($validated['km_end'])) {
                Vehicle::whereKey($validated['vehicle_id'])->update([
                    'current_km' => $validated['km_end'],
                ]);
            }

            return $movement;
        });

        $movement->refresh()->load(['station', 'vehicle', 'user', 'updatedBy']);

        return response()->json($movement, 201);
    }
}
