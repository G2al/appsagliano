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

        $validated = $request->validate([
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')],
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')],
            'date' => ['required', 'date'],
            'km' => ['required', 'integer', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'invoice_number' => ['required', 'string', 'max:255'],
            'notes' => ['required', 'string'],
            'attachment' => ['required', 'file', 'max:16384'],
        ], [
            'required' => 'Il campo :attribute e obbligatorio.',
            'date' => 'Il campo :attribute non e una data valida.',
            'integer' => 'Il campo :attribute deve essere un numero intero.',
            'numeric' => 'Il campo :attribute deve essere un numero.',
            'min.numeric' => 'Il campo :attribute deve essere maggiore o uguale a :min.',
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
            'price' => 'prezzo',
            'invoice_number' => 'numero bolla',
            'notes' => 'dettagli',
            'attachment' => 'allegato',
        ]);

        $kmValue = (int) $validated['km'];
        $attachmentPath = $request->file('attachment')->store('maintenances', 'public');

        $maintenance = Maintenance::create([
            ...$validated,
            'km_current' => $kmValue,
            'km_after' => $kmValue,
            'attachment_path' => $attachmentPath,
            'user_id' => $user->id,
        ]);

        // aggiorna i km del veicolo
        Vehicle::where('id', $validated['vehicle_id'])->update([
            'maintenance_km' => $kmValue,
        ]);

        $maintenance->load(['vehicle', 'supplier', 'user']);

        return response()->json($maintenance, 201);
    }
}
