<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Vehicle;
use App\Models\Maintenance;

class Movement extends Model
{
    use HasFactory;

    private const MAINTENANCE_TOLERANCE_KM = 500;

    protected $fillable = [
        'user_id',
        'updated_by',
        'station_id',
        'vehicle_id',
        'date',
        'km_start',
        'km_end',
        'liters',
        'price',
        'station_charge',
        'adblue',
        'notes',
        'photo_path',
    ];

    protected $casts = [
        'date' => 'datetime',
        'km_start' => 'integer',
        'km_end' => 'integer',
        'liters' => 'decimal:2',
        'km_per_liter' => 'decimal:2',
        'price' => 'decimal:2',
        'station_charge' => 'decimal:2',
        'adblue' => 'decimal:2',
    ];

    protected $appends = [
        'photo_url',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $movement) {
            $kmStart = $movement->km_start;
            $kmEnd = $movement->km_end;
            $liters = $movement->liters;

            $movement->km_per_liter = null;
            if ($kmStart !== null && $kmEnd !== null && $liters !== null) {
                $distance = (float) $kmEnd - (float) $kmStart;
                $litersValue = (float) $liters;
                if ($distance >= 0 && $litersValue > 0) {
                    $movement->km_per_liter = round($distance / $litersValue, 2);
                }
            }
        });

        static::created(function (self $movement) {
            $movement->notifyTelegram();
        });


        static::saved(function (self $movement) {
            $vehicleId = $movement->vehicle_id;
            if ($vehicleId) {
                self::syncVehicleCurrentKm($vehicleId);
                self::notifyUpcomingMaintenanceIfNeeded($vehicleId);
            }

            if ($movement->wasChanged('vehicle_id')) {
                $originalVehicleId = $movement->getOriginal('vehicle_id');
                if ($originalVehicleId) {
                    self::syncVehicleCurrentKm($originalVehicleId);
                }
            }
        });

        static::deleted(function (self $movement) {
            if ($movement->vehicle_id) {
                self::syncVehicleCurrentKm($movement->vehicle_id);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->photo_path);
    }

    protected static function syncVehicleCurrentKm(int $vehicleId): void
    {
        $latestKm = self::query()
            ->where('vehicle_id', $vehicleId)
            ->whereNotNull('km_end')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->value('km_end');

        Vehicle::whereKey($vehicleId)->update([
            // `vehicles.current_km` is NOT NULL: fallback to 0 when no movements remain.
            'current_km' => $latestKm ?? 0,
        ]);
    }

    protected static function notifyUpcomingMaintenanceIfNeeded(int $vehicleId): void
    {
        $vehicle = Vehicle::query()
            ->select('id', 'plate', 'name', 'current_km')
            ->find($vehicleId);

        if (! $vehicle || $vehicle->current_km === null) {
            return;
        }

        $maintenance = Maintenance::query()
            ->where('vehicle_id', $vehicleId)
            ->whereNull('next_maintenance_alert_sent_at')
            ->where(function ($query) {
                $query->where('km_after', '>', 0)
                    ->orWhereNotNull('next_maintenance_date');
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        if (! $maintenance) {
            return;
        }

        $currentKm = (int) $vehicle->current_km;
        $kmReached = $maintenance->shouldSendMaintenanceAlertForKm($currentKm, self::MAINTENANCE_TOLERANCE_KM);
        $dateReached = $maintenance->isNextMaintenanceDateReached();

        if (! $kmReached && ! $dateReached) {
            return;
        }

        if ($dateReached && ! $kmReached) {
            $maintenance->notifyUpcomingMaintenanceByDateTelegram($vehicle, $currentKm);
        } else {
            $maintenance->notifyUpcomingMaintenanceTelegram($vehicle, $currentKm);
        }

        $maintenance->markNextMaintenanceAlertAsSent();
    }

    /**
     * Invia una notifica Telegram quando viene creato un rifornimento.
     */
    public function notifyTelegram(): void
    {
        $token = env('TELEGRAM_MOVEMENTS_BOT_TOKEN', env('TELEGRAM_BOT_TOKEN'));
        $chatId = env('TELEGRAM_MOVEMENTS_CHAT_ID', env('TELEGRAM_CHAT_ID'));

        if (! $token || ! $chatId) {
            return;
        }

        $lines = [];
        $lines[] = '⛽ <b>Nuovo rifornimento</b>';
        $lines[] = '👤 <b>Autore:</b> ' . ($this->user?->full_name ?? $this->user?->name ?? 'N/D');

        if ($this->updated_by && $this->updated_by !== $this->user_id) {
            $lines[] = '🔧 <b>Modificato da:</b> ' . ($this->updatedBy?->name ?? 'Admin');
        }

        $lines[] = '🏪 <b>Stazione:</b> ' . ($this->station?->name ?? 'N/D');
        $lines[] = '🚚 <b>Veicolo:</b> ' . ($this->vehicle?->plate ?? $this->vehicle?->name ?? 'N/D');

        if ($this->date) {
            $lines[] = '📅 <b>Data:</b> ' . $this->date->format('d/m/Y H:i');
        }

        if ($this->km_start !== null || $this->km_end !== null) {
            $lines[] = '🛣️ <b>Km:</b> ' . ($this->km_start ?? 'N/D') . ' → ' . ($this->km_end ?? 'N/D');
        }

        if ($this->liters !== null) {
            $lines[] = '⛽ <b>Litri:</b> ' . number_format((float) $this->liters, 2, ',', '.');
        }

        if ($this->price !== null) {
            $lines[] = '💶 <b>Prezzo:</b> ' . number_format((float) $this->price, 2, ',', '.') . ' €';
        }

        if ($this->adblue !== null) {
            $lines[] = '💧 <b>AdBlue:</b> ' . number_format((float) $this->adblue, 2, ',', '.') . ' L';
        }

        $ticketAvg = $this->km_per_liter;
        if ($ticketAvg === null && $this->km_start !== null && $this->km_end !== null && $this->liters) {
            $distance = (float) $this->km_end - (float) $this->km_start;
            $liters = (float) $this->liters;
            if ($distance >= 0 && $liters > 0) {
                $ticketAvg = $distance / $liters;
            }
        }
        if ($ticketAvg !== null && $ticketAvg >= 0) {
            $lines[] = '📊 <b>Media ticket:</b> ' . number_format((float) $ticketAvg, 2, ',', '.') . ' km/L';
        }

        if ($this->notes) {
            $lines[] = '📝 <b>Note:</b> ' . $this->notes;
        }

        if ($this->photo_url) {
            $lines[] = '📎 <a href="' . $this->photo_url . '">Ricevuta</a>';
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
