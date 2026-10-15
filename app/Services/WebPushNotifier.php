<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushNotifier
{
    /**
     * Invia una notifica push a tutte le subscription attive di un utente.
     * Se una subscription risulta scaduta/non valida (410/404), viene
     * eliminata automaticamente per non ritentare invii inutili.
     *
     * @param  array<string, mixed>  $payload
     */
    public function sendToUser(User $user, array $payload): void
    {
        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = $this->client();

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding ?? 'aesgcm',
                ]),
                json_encode($payload)
            );
        }

        try {
            $reports = $webPush->flush();
        } catch (\Throwable $e) {
            Log::warning('Invio push non riuscito (errore di rete)', ['error' => $e->getMessage()]);

            return;
        }

        foreach ($reports as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            Log::warning('Invio push fallito', [
                'endpoint' => $report->getEndpoint(),
                'reason' => $report->getReason(),
            ]);

            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $report->getEndpoint())->delete();
            }
        }
    }

    private function client(): WebPush
    {
        return new WebPush([
            'VAPID' => [
                'subject' => config('app.url'),
                'publicKey' => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ],
        ]);
    }
}
