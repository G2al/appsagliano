<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'public_key' => ['required', 'string'],
            'auth_token' => ['required', 'string'],
            'content_encoding' => ['nullable', 'string'],
        ]);

        PushSubscription::updateOrCreate(
            ['user_id' => $request->user()->id, 'endpoint' => $validated['endpoint']],
            [
                'public_key' => $validated['public_key'],
                'auth_token' => $validated['auth_token'],
                'content_encoding' => $validated['content_encoding'] ?? 'aesgcm',
            ]
        );

        return response()->json(['message' => 'Iscrizione salvata.']);
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint', $validated['endpoint'])
            ->delete();

        return response()->json(['message' => 'Iscrizione rimossa.']);
    }

    public function vapidPublicKey()
    {
        return response()->json(['publicKey' => config('services.vapid.public_key')]);
    }
}
