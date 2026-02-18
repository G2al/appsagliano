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

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->query('vehicle_id'));
        }

        $perPage = $request->query('per_page');

        if ($perPage === 'all') {
            return response()->json($query->get());
        }

        if ($perPage !== null) {
            $perPageValue = (int) $perPage;
            if ($perPageValue > 0) {
                return response()->json($query->paginate($perPageValue));
            }
        }

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
            'price' => ['required', 'numeric', 'min:0', 'gt:liters'],
            'adblue' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'photo' => ['required', 'file', 'max:16384'],
        ], [
            'required' => 'Il campo :attribute e obbligatorio.',
            'date' => 'Il campo :attribute non e una data valida.',
            'integer' => 'Il campo :attribute deve essere un numero intero.',
            'numeric' => 'Il campo :attribute deve essere un numero.',
            'min.numeric' => 'Il campo :attribute deve essere maggiore o uguale a :min.',
            'price.gt' => 'Il prezzo deve essere maggiore dei litri.',
            'exists' => 'Il campo :attribute non esiste.',
            'photo.file' => 'La ricevuta deve essere un file valido.',
            'photo.max' => 'La ricevuta non puo superare 16MB.',
            'photo.uploaded' => 'Caricamento ricevuta non riuscito. Riprova o usa un file piu piccolo.',
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
                Station::adjustCreditBalance((int) $validated['station_id'], -$stationCharge);
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
