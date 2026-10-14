<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('users', 'name')->where('surname', $request->input('surname'))],
            'surname' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
        ], [
            'name.unique' => 'Esiste già un utente con questo nome e cognome.',
            'phone.unique' => 'Il numero di telefono è già registrato.',
        ]);

        $normalizedPhone = $this->normalizePhone($validated['phone']);

        if (User::where('phone', $normalizedPhone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['Il numero di telefono è già registrato.'],
            ]);
        }

        $email = Str::slug($validated['name'] . ' ' . $validated['surname'], '.') . '@appmegatrasporti.it';

        // La password non la sceglie l'utente: e' generata automaticamente
        // con lo stesso schema usato per gli account creati in blocco
        // dall'admin (nome + "mega2026"), cosi' da subito dopo l'approvazione
        // il suo account si comporta esattamente come uno gia' seedato.
        $password = $this->slugFor($validated['name']) . 'mega2026';

        $user = User::create([
            'name' => $validated['name'],
            'surname' => $validated['surname'],
            'phone' => $normalizedPhone,
            'email' => $email,
            'password' => $password,
            'role' => 'worker',
            'is_approved' => false,
            'self_registered' => true,
            'must_change_password' => true,
        ]);

        return response()->json([
            'message' => 'Registrazione inviata. Sarai abilitato dopo approvazione dell\'admin.',
            'password' => $password,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim($credentials['full_name']);
        $normalized = preg_replace('/\s+/', ' ', $identifier);

        if (preg_match('/^\+?\d+$/', $normalized)) {
            $user = User::where('phone', $this->normalizePhone($normalized))->first();
        } else {
            $concat = DB::connection()->getDriverName() === 'sqlite'
                ? "LOWER(name || ' ' || surname)"
                : 'LOWER(CONCAT(name, " ", surname))';

            $user = User::whereRaw("{$concat} = ?", [mb_strtolower($normalized)])->first();
        }

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'full_name' => ['Credenziali non valide.'],
            ]);
        }

        // Solo chi si e' auto-registrato da /auth/register resta bloccato
        // finche' un admin non lo approva a mano: per lui "non approvato"
        // significa davvero "nessun accesso". Chiunque altro non approvato
        // (creato dall'admin/seeder con password provvisoria) puo' comunque
        // accedere per completare da solo cambio password e verifica
        // documenti (vedi middleware "onboarded" sulle altre rotte).
        if (! $user->is_approved && $user->self_registered) {
            throw ValidationException::withMessages([
                'full_name' => ['Account in attesa di approvazione da parte dell\'admin.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout effettuato',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'new_password' => ['required', 'string', 'min:5', 'confirmed'],
        ], [
            'new_password.min' => 'La nuova password deve contenere almeno 5 caratteri.',
        ]);

        $user->update([
            'password' => $validated['new_password'],
            'must_change_password' => false,
        ]);

        return response()->json([
            'message' => 'Password aggiornata con successo.',
        ]);
    }

    private function slugFor(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::ascii(mb_strtolower($value)));
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '39')) {
            $digits = substr($digits, 2);
        }

        return '+39' . $digits;
    }
}
