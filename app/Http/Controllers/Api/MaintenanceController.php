<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class MaintenanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Maintenance::with(['vehicle', 'supplier', 'user'])
            ->when($user->role !== 'admin', fn ($q) => $q->where('user_id', $user->id))
            ->latest();

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
                'role' => ['Ruolo non autorizzato a creare manutenzioni.'],
            ]);
        }

        $validated = $this->validatePayload($request, attachmentRequired: true);

        $attachmentPath = $request->hasFile('attachment')
            ? $request->file('attachment')->store('maintenances', 'public')
            : null;

        $maintenance = Maintenance::create([
            ...$validated,
            'attachment_path' => $attachmentPath,
            'user_id' => $user->id,
        ]);

        // aggiorna i km del veicolo
        Vehicle::where('id', $validated['vehicle_id'])->update([
            'maintenance_km' => $validated['km_current'],
        ]);

        $maintenance->load(['vehicle', 'supplier', 'user']);

        return response()->json($maintenance, 201);
    }

    public function update(Request $request, Maintenance $maintenance): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'admin' && (int) $maintenance->user_id !== (int) $user->id) {
            abort(403, 'Non puoi modificare una manutenzione di un altro utente.');
        }

        $validated = $this->validatePayload($request, attachmentRequired: false);

        $oldAttachmentPath = $maintenance->attachment_path;

        $validated['attachment_path'] = $request->hasFile('attachment')
            ? $request->file('attachment')->store('maintenances', 'public')
            : $oldAttachmentPath;

        $maintenance->update($validated);

        Vehicle::where('id', $validated['vehicle_id'])->update([
            'maintenance_km' => $validated['km_current'],
        ]);

        if ($request->hasFile('attachment') && $oldAttachmentPath && $oldAttachmentPath !== $maintenance->attachment_path) {
            Storage::disk('public')->delete($oldAttachmentPath);
        }

        $maintenance->refresh()->load(['vehicle', 'supplier', 'user']);

        return response()->json($maintenance);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $attachmentRequired): array
    {
        $validated = $request->validate([
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')],
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')],
            'date' => ['required', 'date'],
            'km' => ['required', 'integer', 'min:0'],
            'km_after' => ['nullable', 'integer', 'gt:km'],
            'next_maintenance_date' => ['nullable', 'date'],
            'price' => ['required', 'numeric', 'min:0'],
            'invoice_number' => ['required', 'string', 'max:255'],
            'notes' => ['required', 'string'],
            'attachment' => [$attachmentRequired ? 'required' : 'nullable', 'file', 'max:16384'],
        ], [
            'required' => 'Il campo :attribute e obbligatorio.',
            'date' => 'Il campo :attribute non e una data valida.',
            'integer' => 'Il campo :attribute deve essere un numero intero.',
            'numeric' => 'Il campo :attribute deve essere un numero.',
            'min.numeric' => 'Il campo :attribute deve essere maggiore o uguale a :min.',
            'km_after.gt' => 'Prossima manutenzione (km) deve essere maggiore di km manutenzione.',
            'exists' => 'Il campo :attribute non esiste.',
            'string' => 'Il campo :attribute deve essere un testo.',
            'attachment.file' => "L'allegato deve essere un file valido.",
            'attachment.max' => "L'allegato non puo superare 16MB.",
            'attachment.uploaded' => "Caricamento allegato non riuscito. Riprova o usa un file piu piccolo.",
        ], [
            'vehicle_id' => 'veicolo',
            'supplier_id' => 'fornitore',
            'date' => 'data',
            'km' => 'km manutenzione',
            'km_after' => 'prossima manutenzione (km)',
            'next_maintenance_date' => 'prossima manutenzione (data)',
            'price' => 'prezzo',
            'invoice_number' => 'numero bolla',
            'notes' => 'dettagli',
            'attachment' => 'allegato',
        ]);

        unset($validated['attachment']);

        $validated['km_current'] = (int) $validated['km'];
        unset($validated['km']);

        $validated['km_after'] = array_key_exists('km_after', $validated) && $validated['km_after'] !== null
            ? (int) $validated['km_after']
            : null;

        $validated['next_maintenance_date'] = $validated['next_maintenance_date'] ?? null;

        return $validated;
    }
}
