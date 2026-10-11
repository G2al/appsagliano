<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VerificationController extends Controller
{
    /**
     * Stato attuale della procedura di primo accesso: cosa manca, cosa e'
     * gia' stato caricato/compilato. Usato dall'app per sapere quale step
     * mostrare.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $verification = UserVerification::query()->where('user_id', $user->id)->first()
            ?? new UserVerification(['user_id' => $user->id]);

        return response()->json($this->buildStatusPayload($user, $verification));
    }

    /**
     * Invio progressivo dei dati di verifica: ogni chiamata puo' contenere
     * anche un solo campo (es. solo l'indirizzo, o solo una foto). Quando
     * tutto il necessario e' presente, l'account viene approvato in automatico.
     */
    public function submit(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'address' => ['nullable', 'string', 'max:255'],
            'identity_front' => ['nullable', 'file', 'image', 'max:8192'],
            'identity_back' => ['nullable', 'file', 'image', 'max:8192'],
            'identity_expiry_date' => ['nullable', 'date'],
            'license' => ['nullable', 'file', 'image', 'max:8192'],
            'license_expiry_date' => ['nullable', 'date'],
            'cqc' => ['nullable', 'file', 'image', 'max:8192'],
            'cqc_expiry_date' => ['nullable', 'date'],
        ], [
            '*.image' => 'Il file :attribute deve essere un\'immagine.',
            '*.max' => 'Il file :attribute non puo\' superare 8MB.',
        ]);

        $verification = UserVerification::query()->firstOrCreate(['user_id' => $user->id]);

        if (filled($validated['address'] ?? null)) {
            $user->update(['address' => $validated['address']]);
        }

        // Le foto si salvano sempre, anche se la scadenza nella stessa
        // richiesta verra' rifiutata subito dopo: non deve mai andare persa
        // una foto valida solo perche' la data inviata insieme a lei non lo e'.
        $fileFields = [
            'identity_front' => 'identity_front_path',
            'identity_back' => 'identity_back_path',
            'license' => 'license_path',
            'cqc' => 'cqc_path',
        ];

        foreach ($fileFields as $input => $column) {
            if (! $request->hasFile($input)) {
                continue;
            }

            $oldPath = $verification->{$column};

            $verification->{$column} = $request->file($input)->store('verifications', 'public');

            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $verification->save();

        $this->guardExpiryDatesRequirePhotos($validated, $verification);

        foreach (['identity_expiry_date', 'license_expiry_date', 'cqc_expiry_date'] as $field) {
            if (array_key_exists($field, $validated) && filled($validated[$field])) {
                $verification->{$field} = $validated[$field];
            }
        }

        $verification->save();

        $this->completeIfReady($user, $verification);

        return response()->json($this->buildStatusPayload($user->fresh(), $verification->fresh()));
    }

    /**
     * Una scadenza ha senso solo se la/e foto del documento a cui si
     * riferisce sono gia' presenti (gia' salvate in precedenza, o incluse
     * in questa stessa richiesta e appena salvate). Blocca con un errore di
     * validazione chiaro altrimenti, invece di salvare una data "orfana".
     * Va chiamato DOPO aver salvato gli eventuali file di questa richiesta.
     *
     * @param  array<string, mixed>  $validated
     */
    private function guardExpiryDatesRequirePhotos(array $validated, UserVerification $verification): void
    {
        $errors = [];

        if (filled($validated['identity_expiry_date'] ?? null)
            && ! (filled($verification->identity_front_path) && filled($verification->identity_back_path))) {
            $errors['identity_expiry_date'] = 'Carica prima entrambe le foto del documento di identita (fronte e retro) prima di inserirne la scadenza.';
        }

        if (filled($validated['license_expiry_date'] ?? null) && ! filled($verification->license_path)) {
            $errors['license_expiry_date'] = 'Carica prima la foto della patente prima di inserirne la scadenza.';
        }

        if (filled($validated['cqc_expiry_date'] ?? null) && ! filled($verification->cqc_path)) {
            $errors['cqc_expiry_date'] = 'Carica prima la foto della CQC prima di inserirne la scadenza.';
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function completeIfReady(User $user, UserVerification $verification): void
    {
        if ($user->is_approved) {
            return;
        }

        if (! filled($user->address) || ! $verification->hasAllDocuments()) {
            return;
        }

        $verification->update(['completed_at' => now()]);
        $user->update(['is_approved' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStatusPayload(User $user, UserVerification $verification): array
    {
        return [
            'is_approved' => $user->is_approved,
            'must_change_password' => $user->must_change_password,
            'address' => $user->address,
            'identity_front_url' => $verification->identity_front_url,
            'identity_back_url' => $verification->identity_back_url,
            'identity_expiry_date' => $verification->identity_expiry_date?->toDateString(),
            'license_url' => $verification->license_url,
            'license_expiry_date' => $verification->license_expiry_date?->toDateString(),
            'cqc_url' => $verification->cqc_url,
            'cqc_expiry_date' => $verification->cqc_expiry_date?->toDateString(),
            'completed_at' => $verification->completed_at?->toIso8601String(),
        ];
    }
}
