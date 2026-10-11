<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsOnboarded
{
    /**
     * Blocca l'accesso al resto dell'app finche' l'utente non ha completato
     * la verifica (documenti + indirizzo) e il cambio password obbligatorio.
     * Il login resta sempre consentito: e' solo l'uso del resto dell'API ad
     * essere vietato in questo stato.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->needsOnboarding()) {
            return response()->json([
                'message' => 'Devi completare la verifica dell\'account prima di continuare.',
                'onboarding_required' => true,
                'is_approved' => $user->is_approved,
                'must_change_password' => $user->must_change_password,
            ], 403);
        }

        return $next($request);
    }
}
