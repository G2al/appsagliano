<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movement;
use App\Models\Station;
use App\Models\StationCard;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MovementController extends Controller
{
    public function kmStart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')],
            'date' => ['required', 'date'],
        ], [
            'required' => 'Il campo :attribute e obbligatorio.',
            'date' => 'Il campo :attribute non e una data valida.',
            'exists' => 'Il campo :attribute non esiste.',
        ], [
            'vehicle_id' => 'veicolo',
            'date' => 'data',
        ]);

        $vehicleId = (int) $validated['vehicle_id'];
        $resolvedKmStart = Movement::resolveKmStartForVehicleAtDate($vehicleId, $validated['date']);

        if ($resolvedKmStart !== null) {
            return response()->json([
                'km_start' => $resolvedKmStart,
                'source' => 'previous_ticket_by_date',
            ]);
        }

        $vehicleCurrentKm = Vehicle::query()
            ->whereKey($vehicleId)
            ->value('current_km');

        return response()->json([
            'km_start' => (int) ($vehicleCurrentKm ?? 0),
            'source' => 'vehicle_current_km',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Movement::with(['station', 'stationCard', 'platform', 'vehicle', 'user', 'updatedBy'])
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

        $validated = $this->validatePayload($request, photoRequired: true);

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store('receipts', 'public');
        }

        $this->applyPaymentMethod($request, $validated);

        $kmStart = Movement::resolveKmStartForVehicleAtDate(
            (int) $validated['vehicle_id'],
            $validated['date']
        ) ?? (int) $validated['km_start'];

        $this->assertKmOrder($kmStart, (int) $validated['km_end']);

        $movement = DB::transaction(function () use ($validated, $user, $kmStart) {
            $movement = Movement::create([
                ...$validated,
                'km_start' => $kmStart,
                'user_id' => $user->id,
            ]);

            if ($movement->station_charge > 0 && $movement->station_id) {
                Station::adjustCreditBalance((int) $movement->station_id, -(float) $movement->station_charge);
            }

            return $movement;
        });

        $movement->refresh()->load(['station', 'stationCard', 'platform', 'vehicle', 'user', 'updatedBy']);

        return response()->json($movement, 201);
    }

    public function update(Request $request, Movement $movement): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'admin' && (int) $movement->user_id !== (int) $user->id) {
            abort(403, 'Non puoi modificare un rifornimento di un altro utente.');
        }

        $validated = $this->validatePayload($request, photoRequired: false);

        $oldPhotoPath = $movement->photo_path;

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store('receipts', 'public');
        } else {
            $validated['photo_path'] = $oldPhotoPath;
        }

        $this->applyPaymentMethod($request, $validated);

        $kmStart = (int) $validated['km_start'];
        $this->assertKmOrder($kmStart, (int) $validated['km_end']);

        $previousStationId = $movement->station_id;
        $previousCharge = (float) ($movement->station_charge ?? 0);

        DB::transaction(function () use ($movement, $validated, $previousStationId, $previousCharge) {
            if ($previousCharge > 0 && $previousStationId) {
                Station::adjustCreditBalance((int) $previousStationId, $previousCharge);
            }

            $movement->update($validated);

            if ($movement->station_charge > 0 && $movement->station_id) {
                Station::adjustCreditBalance((int) $movement->station_id, -(float) $movement->station_charge);
            }
        });

        if ($request->hasFile('photo') && $oldPhotoPath && $oldPhotoPath !== $movement->photo_path) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        $movement->refresh()->load(['station', 'stationCard', 'platform', 'vehicle', 'user', 'updatedBy']);

        return response()->json($movement);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $photoRequired): array
    {
        $validated = $request->validate([
            'station_id' => ['required', Rule::exists('stations', 'id')],
            'platform_id' => ['required', Rule::exists('platforms', 'id')],
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')],
            'date' => ['required', 'date'],
            'km_start' => ['required', 'integer', 'min:0'],
            'km_end' => ['required', 'integer', 'min:0'],
            'liters' => ['required', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0', 'gt:liters'],
            'is_voucher' => ['nullable', 'boolean'],
            'station_card_id' => ['nullable', Rule::exists('station_cards', 'id')],
            'adblue' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'photo' => [$photoRequired ? 'required' : 'nullable', 'file', 'max:16384'],
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
            'platform_id' => 'piattaforma',
            'vehicle_id' => 'veicolo',
            'date' => 'data',
            'km_start' => 'km iniziali',
            'km_end' => 'km finali',
            'liters' => 'litri',
            'price' => 'prezzo',
            'photo' => 'ricevuta',
        ]);

        unset($validated['photo']);

        return $validated;
    }

    /**
     * Valida buono/carta rispetto alla stazione e calcola l'addebito, scrivendo
     * is_voucher e station_charge dentro $validated (passato per riferimento).
     *
     * @param  array<string, mixed>  $validated
     */
    private function applyPaymentMethod(Request $request, array &$validated): void
    {
        $station = Station::select('id', 'credit_balance', 'uses_vouchers', 'uses_credit_cards')->find($validated['station_id']);
        $stationUsesVouchers = (bool) ($station?->uses_vouchers ?? false);
        $stationUsesCreditCards = (bool) ($station?->uses_credit_cards ?? false);
        $requestedVoucher = $request->boolean('is_voucher');
        $stationCardId = $validated['station_card_id'] ?? null;

        if ($requestedVoucher && ! $stationUsesVouchers) {
            throw ValidationException::withMessages([
                'is_voucher' => ['La stazione selezionata non consente rifornimenti con buono.'],
            ]);
        }

        if ($stationCardId !== null && ! $stationUsesCreditCards) {
            throw ValidationException::withMessages([
                'station_card_id' => ['La stazione selezionata non consente rifornimenti con carta di credito.'],
            ]);
        }

        if ($stationUsesCreditCards && $stationCardId === null) {
            throw ValidationException::withMessages([
                'station_card_id' => ['Seleziona la carta di credito usata per questo rifornimento.'],
            ]);
        }

        if ($stationCardId !== null) {
            $cardBelongsToStation = StationCard::where('id', $stationCardId)
                ->where('station_id', $validated['station_id'])
                ->exists();

            if (! $cardBelongsToStation) {
                throw ValidationException::withMessages([
                    'station_card_id' => ['La carta selezionata non appartiene alla stazione scelta.'],
                ]);
            }
        }

        $isVoucher = $stationUsesVouchers && $requestedVoucher;

        $validated['is_voucher'] = $isVoucher;
        $validated['station_charge'] = ($station && $station->credit_balance !== null && ! $stationUsesCreditCards)
            ? ($isVoucher ? 0.0 : (float) $validated['price'])
            : 0.0;
    }

    private function assertKmOrder(int $kmStart, int $kmEnd): void
    {
        if ($kmEnd < $kmStart) {
            throw ValidationException::withMessages([
                'km_end' => ['I km finali devono essere maggiori o uguali ai km iniziali.'],
            ]);
        }
    }
}
