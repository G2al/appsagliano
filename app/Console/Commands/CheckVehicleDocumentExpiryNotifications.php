<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\VehicleDocumentExpiry;
use App\Models\VehicleDocumentExpiryNotification;
use App\Services\WebPushNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckVehicleDocumentExpiryNotifications extends Command
{
    protected $signature = 'vehicles:check-document-expiry';

    protected $description = 'Controlla i documenti veicolo in scadenza (30/15/7 giorni) e invia una push ad admin/manutenzione/documenti';

    /**
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

        foreach (VehicleDocumentExpiry::query()->whereNotNull('expiry_date')->get() as $document) {
            $daysRemaining = $today->diffInDays($document->expiry_date, false);

            $threshold = $this->matchThreshold((int) $daysRemaining);
            if ($threshold === null) {
                continue;
            }

            $alreadySent = VehicleDocumentExpiryNotification::query()
                ->where('vehicle_document_id', $document->vehicle_document_id)
                ->where('threshold_days', $threshold)
                ->where('expiry_date', $document->expiry_date)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $expiryFormatted = $document->expiry_date->format('d/m/Y');
            $whenText = $this->whenText((int) $daysRemaining);

            $payload = [
                'title' => 'Documento veicolo in scadenza',
                'body' => "{$document->vehicle_label} - {$document->document_type} {$whenText} ({$expiryFormatted}).",
                'url' => '/admin/scadenze-veicoli',
            ];

            foreach ($recipients as $recipient) {
                $notifier->sendToUser($recipient, $payload);
            }

            VehicleDocumentExpiryNotification::create([
                'vehicle_document_id' => $document->vehicle_document_id,
                'threshold_days' => $threshold,
                'expiry_date' => $document->expiry_date,
                'sent_at' => now(),
            ]);

            $sent++;
        }

        $this->info("Notifiche di scadenza veicoli inviate: {$sent}");

        return self::SUCCESS;
    }

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

    private function whenText(int $daysRemaining): string
    {
        if ($daysRemaining === 0) {
            return 'scade oggi';
        }

        if ($daysRemaining === 1) {
            return 'scade domani';
        }

        return "scade tra {$daysRemaining} giorni";
    }
}
