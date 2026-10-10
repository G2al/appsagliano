<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Platform;
use App\Models\Trip;
use App\Services\TripDistanceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TripController extends Controller
{
    public function calculateDistance(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->role, ['worker', 'admin'], true)) {
            throw ValidationException::withMessages([
                'role' => ['Ruolo non autorizzato a calcolare viaggi.'],
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
            'platform_id' => ['required', Rule::exists('platforms', 'id')],
            'destinations' => ['required', 'array', 'min:1'],
            'destinations.*' => ['required', 'string', 'max:255'],
        ], [
            'required' => 'Il campo :attribute e obbligatorio.',
            'exists' => 'Il campo :attribute non esiste.',
            'destinations.min' => 'Inserisci almeno una destinazione.',
        ], [
            'platform_id' => 'piattaforma',
            'destinations' => 'destinazioni',
            'destinations.*' => 'destinazione',
        ]);

        $platform = Platform::query()->find($validated['platform_id']);

        $result = app(TripDistanceCalculator::class)->preview($platform, $validated['destinations']);

        return response()->json($result);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Trip::with(['platform', 'vehicle', 'user', 'attachments'])
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
        ]);

        foreach ($this->collectAttachmentFiles($request) as $file) {
            $trip->attachments()->create([
                'path' => $file->store('trips', 'public'),
            ]);
        }

        app(TripDistanceCalculator::class)->calculate($trip);

        $trip->load(['platform', 'vehicle', 'user', 'attachments']);

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

        $trip->update($validated);

        if ($trip->wasChanged(['platform_id', 'destinations'])) {
            app(TripDistanceCalculator::class)->calculate($trip);
        }

        $removeIds = collect($request->input('remove_attachment_ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id);

        if ($removeIds->isNotEmpty()) {
            $trip->attachments()
                ->whereIn('id', $removeIds)
                ->get()
                ->each(function ($attachment) {
                    Storage::disk('public')->delete($attachment->path);
                    $attachment->delete();
                });
        }

        foreach ($this->collectAttachmentFiles($request) as $file) {
            $trip->attachments()->create([
                'path' => $file->store('trips', 'public'),
            ]);
        }

        $trip->refresh()->load(['platform', 'vehicle', 'user', 'attachments']);

        return response()->json($trip);
    }

    /**
     * @return array<int, \Illuminate\Http\UploadedFile>
     */
    private function collectAttachmentFiles(Request $request): array
    {
        $files = $request->file('attachments', []);
        $files = is_array($files) ? $files : [$files];

        if ($request->hasFile('attachment')) {
            $files[] = $request->file('attachment');
        }

        return array_values(array_filter($files));
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
            'delivery_note_number' => ['required', 'regex:/^[0-9-]{1,30}$/'],
            'attachment' => [$attachmentRequired ? 'required_without:attachments' : 'nullable', 'file', 'max:16384'],
            'attachments' => [$attachmentRequired ? 'required_without:attachment' : 'nullable', 'array', 'min:1'],
            'attachments.*' => ['file', 'max:16384'],
        ], [
            'required' => 'Il campo :attribute e obbligatorio.',
            'date' => 'Il campo :attribute non e una data valida.',
            'exists' => 'Il campo :attribute non esiste.',
            'in' => 'Il campo :attribute non e valido.',
            'destinations.min' => 'Inserisci almeno una destinazione.',
            'delivery_note_number.regex' => 'Il campo bolla puo contenere solo numeri e trattini.',
            'attachment.required_without' => "L'allegato e obbligatorio.",
            'attachment.file' => "L'allegato deve essere un file valido.",
            'attachment.max' => "L'allegato non puo superare 16MB.",
            'attachments.required_without' => 'Carica almeno un allegato.',
            'attachments.min' => 'Carica almeno un allegato.',
            'attachments.*.file' => 'Ogni allegato deve essere un file valido.',
            'attachments.*.max' => 'Ogni allegato non puo superare 16MB.',
        ], [
            'date' => 'data',
            'platform_id' => 'piattaforma',
            'vehicle_id' => 'veicolo',
            'destinations' => 'destinazioni',
            'destinations.*' => 'destinazione',
            'goods_type' => 'dicitura',
            'delivery_note_number' => 'bolla',
            'attachment' => 'allegato',
            'attachments' => 'allegati',
        ]);

        unset($validated['attachment'], $validated['attachments']);

        return $validated;
    }
}
