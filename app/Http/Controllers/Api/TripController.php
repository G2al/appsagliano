<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        $validated = $this->validatePayload($request, attachmentRequired: true);

        $trip = Trip::create([
            'user_id' => $user->id,
            ...$validated,
            'attachment_path' => $request->file('attachment')->store('trips', 'public'),
        ]);

        $trip->load(['platform', 'vehicle', 'user']);

        return response()->json($trip, 201);
    }

    public function update(Request $request, Trip $trip): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->role === 'admin';

        if (! $isAdmin && (int) $trip->user_id !== (int) $user->id) {
            abort(403, 'Non puoi modificare un viaggio di un altro utente.');
        }

        if (! $isAdmin && $trip->is_certified) {
            abort(403, 'Il viaggio e gia stato pagato e non puo piu essere modificato.');
        }

        $validated = $this->validatePayload($request, attachmentRequired: false);

        $oldAttachmentPath = $trip->attachment_path;

        if ($request->hasFile('attachment')) {
            $validated['attachment_path'] = $request->file('attachment')->store('trips', 'public');
        }

        $trip->update($validated);

        if ($request->hasFile('attachment') && $oldAttachmentPath && isset($validated['attachment_path']) && $oldAttachmentPath !== $validated['attachment_path']) {
            Storage::disk('public')->delete($oldAttachmentPath);
        }

        $trip->refresh()->load(['platform', 'vehicle', 'user']);

        return response()->json($trip);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $attachmentRequired): array
    {
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
            'attachment' => [$attachmentRequired ? 'required' : 'nullable', 'file', 'max:16384'],
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

        unset($validated['attachment']);

        return $validated;
    }
}
