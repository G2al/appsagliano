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
        'next_maintenance_date',
        'price',
        'invoice_number',
        'notes',
        'attachment_path',
    ];

    protected $casts = [
        'date' => 'datetime',
        'km_current' => 'integer',
        'km_after' => 'integer',
        'next_maintenance_date' => 'date',
        'price' => 'decimal:2',
        'next_maintenance_alert_sent_at' => 'datetime',
    ];

    protected $appends = [
        'attachment_url',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $maintenance) {
            if (
                $maintenance->isDirty('km_after') ||
                $maintenance->isDirty('next_maintenance_date') ||
                $maintenance->isDirty('vehicle_id')
            ) {
                $maintenance->next_maintenance_alert_sent_at = null;
            }
        });

        static::created(function (self $maintenance) {
            $maintenance->notifyTelegram();
        });
    }

    public function shouldSendMaintenanceAlertForKm(int $currentKm, int $toleranceKm = 500): bool
    {
        $nextKm = (int) ($this->km_after ?? 0);

        if ($nextKm <= 0) {
            return false;
        }

        return $currentKm >= ($nextKm - $toleranceKm);
    }

    public function isNextMaintenanceDateReached(?string $referenceDate = null): bool
    {
        if (! $this->next_maintenance_date) {
            return false;
        }

        $today = $referenceDate ?: now()->toDateString();

        return $this->next_maintenance_date->toDateString() <= $today;
    }

    public function markNextMaintenanceAlertAsSent(): void
    {
        $this->forceFill([
            'next_maintenance_alert_sent_at' => now(),
        ])->saveQuietly();
    }

    private function maintenanceAlertTelegramConfig(): array
    {
        return [
            'token' => env('TELEGRAM_MAINTENANCE_BOT_TOKEN'),
            'chat_id' => env('TELEGRAM_MAINTENANCE_CHAT_ID'),
        ];
    }

    public function notifyUpcomingMaintenanceTelegram(Vehicle $vehicle, int $currentKm): void
    {
        $config = $this->maintenanceAlertTelegramConfig();
        $token = $config['token'] ?? null;
        $chatId = $config['chat_id'] ?? null;

        if (! $token || ! $chatId) {
            return;
        }

        $nextKm = $this->km_after ?? 0;
        if ($nextKm <= 0) {
            return;
        }

        $vehicleLabel = trim(($vehicle->plate ? $vehicle->plate . ' - ' : '') . ($vehicle->name ?? ''));
        $vehicleLabel = $vehicleLabel !== '' ? $vehicleLabel : 'Veicolo';

        $remaining = max($nextKm - $currentKm, 0);

        $lines = [];
        $lines[] = '🚨⚠️ <b>AVVISO MANUTENZIONE</b>';
        $lines[] = '🚚 <b>Veicolo:</b> ' . $vehicleLabel;
        $lines[] = '📍 <b>Soglia raggiunta:</b> entro 500 km dalla manutenzione';
        $lines[] = '🧭 <b>Km attuali:</b> ' . number_format((float) $currentKm, 0, ',', '.');
        $lines[] = '🛠️ <b>Prossima manutenzione:</b> ' . number_format((float) $nextKm, 0, ',', '.');
        $lines[] = '⏳ <b>Km mancanti:</b> ' . number_format((float) $remaining, 0, ',', '.');

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

    public function notifyUpcomingMaintenanceByDateTelegram(Vehicle $vehicle, int $currentKm): void
    {
        $config = $this->maintenanceAlertTelegramConfig();
        $token = $config['token'] ?? null;
        $chatId = $config['chat_id'] ?? null;

        if (! $token || ! $chatId) {
            return;
        }

        $vehicleLabel = trim(($vehicle->plate ? $vehicle->plate . ' - ' : '') . ($vehicle->name ?? ''));
        $vehicleLabel = $vehicleLabel !== '' ? $vehicleLabel : 'Veicolo';

        $nextDate = $this->next_maintenance_date?->format('d/m/Y') ?? 'N/D';
        $nextKm = (int) ($this->km_after ?? 0);
        $remaining = $nextKm > 0 ? max($nextKm - $currentKm, 0) : null;

        $lines = [];
        $lines[] = '<b>AVVISO MANUTENZIONE</b>';
        $lines[] = '<b>Veicolo:</b> ' . $vehicleLabel;
        $lines[] = '<b>Scadenza raggiunta:</b> data prossima manutenzione';
        $lines[] = '<b>Data prossima manutenzione:</b> ' . $nextDate;
        $lines[] = '<b>Km attuali:</b> ' . number_format((float) $currentKm, 0, ',', '.');

        if ($nextKm > 0) {
            $lines[] = '<b>Prossima manutenzione (km):</b> ' . number_format((float) $nextKm, 0, ',', '.');
            $lines[] = '<b>Km mancanti:</b> ' . number_format((float) $remaining, 0, ',', '.');
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

    public static function notifyDueDateAlerts(): int
    {
        $today = now()->toDateString();

        $vehicleIds = self::query()
            ->whereNull('next_maintenance_alert_sent_at')
            ->whereNotNull('next_maintenance_date')
            ->whereDate('next_maintenance_date', '<=', $today)
            ->pluck('vehicle_id')
            ->unique()
            ->values();

        $notified = 0;

        foreach ($vehicleIds as $vehicleId) {
            $maintenance = self::query()
                ->with(['vehicle:id,plate,name,current_km'])
                ->where('vehicle_id', $vehicleId)
                ->whereNull('next_maintenance_alert_sent_at')
                ->where(function ($query) {
                    $query->where('km_after', '>', 0)
                        ->orWhereNotNull('next_maintenance_date');
                })
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->first();

            if (! $maintenance || ! $maintenance->isNextMaintenanceDateReached($today)) {
                continue;
            }

            $vehicle = $maintenance->vehicle;
            if (! $vehicle) {
                continue;
            }

            $maintenance->notifyUpcomingMaintenanceByDateTelegram($vehicle, (int) ($vehicle->current_km ?? 0));
            $maintenance->markNextMaintenanceAlertAsSent();
            $notified++;
        }

        return $notified;
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
