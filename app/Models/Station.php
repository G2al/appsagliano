<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Http;
use App\Models\Movement;

class Station extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'credit_balance',
    ];

    protected $casts = [
        'credit_balance' => 'decimal:2',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    protected static function booted(): void
    {
        static::updated(function (self $station): void {
            $threshold = (float) (env('STATION_CREDIT_THRESHOLD', 5000));
            $old = $station->getOriginal('credit_balance');
            $new = $station->credit_balance;

            // Notifica discesa sotto soglia (solo se prima era sopra o uguale)
            if ($new !== null && $new < $threshold && ($old === null || $old >= $threshold)) {
                $station->notifyCreditBelowThreshold($threshold, $new);
            }

            // Notifica risalita sopra soglia (ricarica)
            if ($new !== null && $new >= $threshold && ($old !== null && $old < $threshold)) {
                $station->notifyCreditRestored($threshold, $new);
            }
        });
    }

    protected function notifyCreditBelowThreshold(float $threshold, float $balance): void
    {
        $token = env('TELEGRAM_CREDIT_BOT_TOKEN');
        $chatId = env('TELEGRAM_CREDIT_CHAT_ID');

        if (! $token || ! $chatId) {
            return;
        }

        $lines = [];
        $lines[] = '⚠️ <b>Credito stazione basso</b>';
        $lines[] = '🏪 <b>Stazione:</b> ' . ($this->name ?? 'N/D');
        $lines[] = '💰 <b>Saldo:</b> ' . number_format($balance, 2, ',', '.') . ' €';
        $lines[] = '🔻 <b>Soglia:</b> ' . number_format($threshold, 2, ',', '.') . ' €';

        $text = implode("\n", $lines);

        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            // Non bloccare
        }
    }

    protected function notifyCreditRestored(float $threshold, float $balance): void
    {
        $token = env('TELEGRAM_CREDIT_BOT_TOKEN');
        $chatId = env('TELEGRAM_CREDIT_CHAT_ID');

        if (! $token || ! $chatId) {
            return;
        }

        $lines = [];
        $lines[] = '✅ <b>Credito ricaricato</b>';
        $lines[] = '🏪 <b>Stazione:</b> ' . ($this->name ?? 'N/D');
        $lines[] = '💰 <b>Saldo:</b> ' . number_format($balance, 2, ',', '.') . ' €';
        $lines[] = '📈 <b>Soglia:</b> ' . number_format($threshold, 2, ',', '.') . ' €';

        $text = implode("\n", $lines);

        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            // Non bloccare
        }
    }
}
