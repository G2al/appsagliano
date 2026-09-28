<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TripController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Trip::with(['platform', 'vehicle', 'user'])
            ->when($user->role !== 'admin', fn ($q) => $q->where('user_id', $user->id))
            ->latest('date')
            ->latest('id');

        $perPage = $request->query('per_page');

        if ($perPage === 'all') {
            return response()->json($query->get());
        }

        $perPageValue = (int) $perPage;

        return response()->json($query->paginate($perPageValue > 0 ? $perPageValue : 20));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->role, ['worker', 'admin'], true)) {
            throw ValidationException::withMessages([
                'role' => ['Ruolo non autorizzato a creare viaggi.'],
            ]);
        }

        $request->merge([
            'destinations' => collect($request->input('destinations', []))
                ->map(fn ($value) => is_string($value) ? trim($value) : $value)
                ->filter(fn ($value) => $value !== '' && $value !== null)
                ->values()
                ->all(),
        ]);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'platform_id' => ['required', Rule::exists('platforms', 'id')],
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')],
            'destinations' => ['required', 'array', 'min:1'],
            'destinations.*' => ['required', 'string', 'max:255'],
            'goods_type' => ['required', Rule::in(array_keys(Trip::goodsTypeOptions()))],
            'delivery_note_number' => ['required', 'regex:/^\d{1,30}$/'],
            'attachment' => ['required', 'file', 'max:16384'],
        ], [
            'required' => 'Il campo :attribute e obbligatorio.',
            'date' => 'Il campo :attribute non e una data valida.',
            'exists' => 'Il campo :attribute non esiste.',
            'in' => 'Il campo :attribute non e valido.',
            'destinations.min' => 'Inserisci almeno una destinazione.',
            'delivery_note_number.regex' => 'Il campo bolla deve contenere solo numeri.',
            'attachment.file' => "L'allegato deve essere un file valido.",
            'attachment.max' => "L'allegato non puo superare 16MB.",
            'attachment.uploaded' => 'Caricamento allegato non riuscito. Riprova o usa un file piu piccolo.',
        ], [
            'date' => 'data',
            'platform_id' => 'piattaforma',
            'vehicle_id' => 'veicolo',
            'destinations' => 'destinazioni',
            'destinations.*' => 'destinazione',
            'goods_type' => 'dicitura',
            'delivery_note_number' => 'bolla',
            'attachment' => 'allegato',
        ]);

        $trip = Trip::create([
            'user_id' => $user->id,
            'platform_id' => $validated['platform_id'],
            'vehicle_id' => $validated['vehicle_id'],
            'date' => $validated['date'],
            'destinations' => $validated['destinations'],
            'goods_type' => $validated['goods_type'],
            'delivery_note_number' => $validated['delivery_note_number'],
            'attachment_path' => $request->file('attachment')->store('trips', 'public'),
        ]);

        $trip->load(['platform', 'vehicle', 'user']);

        return response()->json($trip, 201);
    }
}
