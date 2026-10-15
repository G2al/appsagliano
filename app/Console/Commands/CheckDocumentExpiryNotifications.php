<?php

namespace App\Console\Commands;

use App\Models\DocumentExpiry;
use App\Models\ExpiryNotification;
use App\Models\User;
use App\Services\WebPushNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckDocumentExpiryNotifications extends Command
{
    protected $signature = 'verifications:check-expiry';

    protected $description = 'Controlla i documenti di verifica in scadenza (30/15/7 giorni) e invia una push ad admin/manutenzione/documenti';

    /**
     * Soglie in giorni per cui inviare un alert, dalla piu vicina alla piu
     * lontana: serve a far vincere la soglia piu stretta ancora valida
     * (es. 5 giorni rimanenti deve risultare soglia 7, non 30).
     *
     * @var array<int, int>
     */
    private const THRESHOLDS = [7, 15, 30];

    public function handle(): int
    {
        $today = Carbon::today();
        $sent = 0;

        $recipients = User::all()->filter(fn (User $user) => $user->canReceiveExpiryNotifications());

        if ($recipients->isEmpty()) {
            return self::SUCCESS;
        }

        $notifier = app(WebPushNotifier::class);

        foreach (DocumentExpiry::query()->whereNotNull('expiry_date')->get() as $document) {
            $daysRemaining = $today->diffInDays($document->expiry_date, false);

            $threshold = $this->matchThreshold((int) $daysRemaining);
            if ($threshold === null) {
                continue;
            }

            $alreadySent = ExpiryNotification::query()
                ->where('user_verification_id', $document->user_verification_id)
                ->where('document_type', $document->document_type)
                ->where('threshold_days', $threshold)
                ->where('expiry_date', $document->expiry_date)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $label = DocumentExpiry::LABELS[$document->document_type] ?? $document->document_type;
            $expiryFormatted = $document->expiry_date->format('d/m/Y');

            $payload = [
                'title' => "Documento in scadenza: {$document->user_full_name}",
                'body' => "{$label} scade il {$expiryFormatted} (tra {$threshold} giorni o meno).",
                'url' => '/admin/scadenze',
            ];

            foreach ($recipients as $recipient) {
                $notifier->sendToUser($recipient, $payload);
            }

            ExpiryNotification::create([
                'user_verification_id' => $document->user_verification_id,
                'document_type' => $document->document_type,
                'threshold_days' => $threshold,
                'expiry_date' => $document->expiry_date,
                'sent_at' => now(),
            ]);

            $sent++;
        }

        $this->info("Notifiche di scadenza inviate: {$sent}");

        return self::SUCCESS;
    }

    /**
     * Restituisce la soglia (30/15/7) piu stretta ancora valida per i
     * giorni rimanenti, oppure null se il documento non e in nessuna
     * finestra di allerta (o e scaduto, caso gestito a parte lato UI).
     */
    private function matchThreshold(int $daysRemaining): ?int
    {
        if ($daysRemaining < 0) {
            return null;
        }

        foreach (self::THRESHOLDS as $threshold) {
            if ($daysRemaining <= $threshold) {
                return $threshold;
            }
        }

        return null;
    }
}
