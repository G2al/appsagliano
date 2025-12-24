<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
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

        $email = Str::slug($validated['name'] . ' ' . $validated['surname'], '.') . '@sagliano.com';

        $user = User::create([
            'name' => $validated['name'],
            'surname' => $validated['surname'],
            'phone' => $normalizedPhone,
            'email' => $email,
            'password' => $validated['password'],
            'role' => 'worker',
            'is_approved' => false,
        ]);

        return response()->json([
            'message' => 'Registrazione inviata. Sarai abilitato dopo approvazione dell\'admin.',
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
            $user = User::whereRaw('LOWER(CONCAT(name, " ", surname)) = ?', [mb_strtolower($normalized)])->first();
        }

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'full_name' => ['Credenziali non valide.'],
            ]);
        }

        if (! $user->is_approved) {
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
