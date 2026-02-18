<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Http;

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

    public static function adjustCreditBalance(int $stationId, float $delta): void
    {
        if ($delta === 0.0) {
            return;
        }

        $station = self::query()->find($stationId);

        if (! $station || $station->credit_balance === null) {
            return;
        }

        $station->credit_balance = round(((float) $station->credit_balance) + $delta, 2);
        $station->save();
    }

    protected static function booted(): void
    {
        static::updated(function (self $station): void {
            $threshold = (float) env('STATION_CREDIT_THRESHOLD', 5000);
            $oldRaw = $station->getOriginal('credit_balance');
            $newRaw = $station->credit_balance;

            if ($newRaw === null) {
                return;
            }

            $old = $oldRaw !== null ? (float) $oldRaw : null;
            $new = (float) $newRaw;

            // Requisito: notifica sempre quando viene aggiunto credito.
            if ($old !== null && $new > $old) {
                $station->notifyCreditAdded($new);
            }

            // Requisito: notifica quando il credito scende sotto soglia.
            if ($new < $threshold && ($old === null || $old >= $threshold)) {
                $station->notifyCreditBelowThreshold($threshold, $new);
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
        $lines[] = '&#9888;&#65039; <b>Credito stazione basso</b>';
        $lines[] = '&#127970; <b>Stazione:</b> ' . ($this->name ?? 'N/D');
        $lines[] = '&#128176; <b>Saldo:</b> ' . number_format($balance, 2, ',', '.') . ' &euro;';
        $lines[] = '&#128315; <b>Soglia:</b> ' . number_format($threshold, 2, ',', '.') . ' &euro;';

        $text = implode("\n", $lines);

        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            // Non bloccare il flusso.
        }
    }

    protected function notifyCreditAdded(float $newBalance): void
    {
        $token = env('TELEGRAM_CREDIT_BOT_TOKEN');
        $chatId = env('TELEGRAM_CREDIT_CHAT_ID');

        if (! $token || ! $chatId) {
            return;
        }
        $threshold = (float) env('STATION_CREDIT_THRESHOLD', 5000);

        $lines = [];
        $lines[] = '&#9989; <b>Credito ricaricato</b>';
        $lines[] = '&#127970; <b>Stazione:</b> ' . ($this->name ?? 'N/D');
        $lines[] = '&#128176; <b>Saldo:</b> ' . number_format($newBalance, 2, ',', '.') . ' &euro;';
        $lines[] = '&#128200; <b>Soglia:</b> ' . number_format($threshold, 2, ',', '.') . ' &euro;';

        $text = implode("\n", $lines);

        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            // Non bloccare il flusso.
        }
    }
}
