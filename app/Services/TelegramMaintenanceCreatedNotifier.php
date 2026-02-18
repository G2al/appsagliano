<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramMaintenanceCreatedNotifier
{
    public function send(string $text): void
    {
        $token = env('TELEGRAM_MAINTENANCE_CREATED_BOT_TOKEN', env('TELEGRAM_MAINTENANCE_BOT_TOKEN'));
        $chatId = env('TELEGRAM_MAINTENANCE_CREATED_CHAT_ID', env('TELEGRAM_MAINTENANCE_CHAT_ID'));

        if (! $token || ! $chatId) {
            return;
        }

        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            // non bloccare il flusso in caso di errore telegram
        }
    }
}
