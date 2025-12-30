<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class Maintenance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'supplier_id',
        'date',
        'km_current',
        'km_after',
        'price',
        'invoice_number',
        'notes',
        'attachment_path',
    ];

    protected $casts = [
        'date' => 'datetime',
        'km_current' => 'integer',
        'km_after' => 'integer',
        'price' => 'decimal:2',
    ];

    protected $appends = [
        'attachment_url',
    ];

    protected static function booted(): void
    {
        static::created(function (self $maintenance) {
            $maintenance->notifyTelegram();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return Storage::disk('public')->url($this->attachment_path);
    }

    /**
     * Invia una notifica Telegram quando viene creata una manutenzione.
     */
    public function notifyTelegram(): void
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_CHAT_ID');

        if (! $token || ! $chatId) {
            return;
        }

        $lines = [];
        $lines[] = '🔧 <b>Nuova manutenzione</b>';
        $lines[] = '👤 <b>Autore:</b> ' . ($this->user?->full_name ?? $this->user?->name ?? 'N/D');
        $lines[] = '🚚 <b>Veicolo:</b> ' . ($this->vehicle?->plate ?? $this->vehicle?->name ?? 'N/D');
        $lines[] = '🏪 <b>Fornitore:</b> ' . ($this->supplier?->name ?? 'N/D');

        if ($this->date) {
            $lines[] = '📅 <b>Data:</b> ' . $this->date->format('d/m/Y H:i');
        }

        if ($this->km_current !== null) {
            $lines[] = '📏 <b>Km manutenzione:</b> ' . number_format((float) $this->km_current, 0, ',', '.');
        }

        if ($this->invoice_number) {
            $lines[] = '🧾 <b>Numero bolla:</b> ' . $this->invoice_number;
        }

        if ($this->price !== null) {
            $lines[] = '💶 <b>Prezzo:</b> ' . number_format((float) $this->price, 2, ',', '.') . ' €';
        }

        if ($this->notes) {
            $lines[] = '📝 <b>Dettagli:</b> ' . $this->notes;
        }

        if ($this->attachment_url) {
            $lines[] = '📎 <a href="' . $this->attachment_url . '">Allegato</a>';
        }

        $text = implode("\n", $lines);

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
