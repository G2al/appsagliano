<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Vehicle;
use App\Models\Maintenance;

class Movement extends Model
{
    use HasFactory;
    use SoftDeletes;

    private const MAINTENANCE_TOLERANCE_KM = 500;
    private const KM_PER_LITER_PRECISION = 2;

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
        'is_voucher',
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
        'is_voucher' => 'boolean',
        'adblue' => 'decimal:2',
    ];

    protected $appends = [
        'photo_url',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $movement) {
            $movement->km_per_liter = self::calculateKmPerLiter(
                $movement->km_start,
                $movement->km_end,
                $movement->liters,
            );
        });

        static::deleting(function (self $movement): void {
            if ($movement->trashed()) {
                return;
            }

            self::refundStationCharge($movement);
        });

        static::created(function (self $movement) {
            $movement->notifyTelegram();
        });


        static::saved(function (self $movement) {
            foreach (self::affectedVehicleIds($movement) as $vehicleId) {
                self::realignVehicleSequence($vehicleId);
            }

            if ($movement->vehicle_id) {
                self::notifyUpcomingMaintenanceIfNeeded((int) $movement->vehicle_id);
            }
        });

        static::deleted(function (self $movement) {
            if ($movement->vehicle_id) {
                self::realignVehicleSequence((int) $movement->vehicle_id);
            }
        });

        static::restored(function (self $movement): void {
            self::applyStationCharge($movement);

            if ($movement->vehicle_id) {
                self::realignVehicleSequence((int) $movement->vehicle_id);
                self::notifyUpcomingMaintenanceIfNeeded((int) $movement->vehicle_id);
            }
        });
    }

    /**
     * @return array{vehicles:int,movements:int,updated:int}
     */
    public static function realignAllVehicleSequences(): array
    {
        $vehicleIds = self::query()
            ->whereNotNull('vehicle_id')
            ->distinct()
            ->orderBy('vehicle_id')
            ->pluck('vehicle_id');

        $summary = [
            'vehicles' => 0,
            'movements' => 0,
            'updated' => 0,
        ];

        foreach ($vehicleIds as $vehicleId) {
            $stats = self::realignVehicleSequence((int) $vehicleId);
            $summary['vehicles']++;
            $summary['movements'] += $stats['movements'];
            $summary['updated'] += $stats['updated'];
        }

        return $summary;
    }

    /**
     * @return array{movements:int,updated:int}
     */
    public static function realignVehicleSequence(int $vehicleId): array
    {
        if ($vehicleId <= 0) {
            return ['movements' => 0, 'updated' => 0];
        }

        $movements = self::query()
            ->where('vehicle_id', $vehicleId)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $previousKmEnd = null;
        $updated = 0;

        foreach ($movements as $movement) {
            $expectedKmStart = $previousKmEnd ?? $movement->km_start;
            $expectedKmPerLiter = self::calculateKmPerLiter(
                $expectedKmStart,
                $movement->km_end,
                $movement->liters,
            );

            if (
                ! self::sameIntegerValue($movement->km_start, $expectedKmStart)
                || ! self::sameDecimalValue($movement->km_per_liter, $expectedKmPerLiter)
            ) {
                $movement->timestamps = false;
                $movement->km_start = $expectedKmStart;
                $movement->km_per_liter = $expectedKmPerLiter;
                $movement->saveQuietly();
                $updated++;
            }

            $previousKmEnd = $movement->km_end !== null
                ? (int) $movement->km_end
                : null;
        }

        self::syncVehicleCurrentKm($vehicleId);

        return [
            'movements' => $movements->count(),
            'updated' => $updated,
        ];
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

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($this->photo_path);
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

    protected static function refundStationCharge(self $movement): void
    {
        $stationId = $movement->station_id;
        $charge = (float) ($movement->station_charge ?? 0);

        if (! $stationId || $charge <= 0) {
            return;
        }

        Station::adjustCreditBalance((int) $stationId, $charge);
    }

    protected static function applyStationCharge(self $movement): void
    {
        $stationId = $movement->station_id;
        $charge = (float) ($movement->station_charge ?? 0);

        if (! $stationId || $charge <= 0) {
            return;
        }

        Station::adjustCreditBalance((int) $stationId, -$charge);
    }

    public static function resolveKmStartForVehicleAtDate(int $vehicleId, string|Carbon $referenceDate): ?int
    {
        $kmStart = self::query()
            ->where('vehicle_id', $vehicleId)
            ->whereNotNull('km_end')
            ->where('date', '<=', $referenceDate)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->value('km_end');

        return $kmStart !== null ? (int) $kmStart : null;
    }

    protected static function calculateKmPerLiter(mixed $kmStart, mixed $kmEnd, mixed $liters): ?float
    {
        if ($kmStart === null || $kmEnd === null || $liters === null) {
            return null;
        }

        $distance = (float) $kmEnd - (float) $kmStart;
        $litersValue = (float) $liters;

        if ($distance < 0 || $litersValue <= 0) {
            return null;
        }

        return round($distance / $litersValue, self::KM_PER_LITER_PRECISION);
    }

    /**
     * @return array<int, int>
     */
    protected static function affectedVehicleIds(self $movement): array
    {
        $vehicleIds = [];

        if ($movement->vehicle_id) {
            $vehicleIds[] = (int) $movement->vehicle_id;
        }

        if ($movement->wasChanged('vehicle_id')) {
            $originalVehicleId = $movement->getOriginal('vehicle_id');
            if ($originalVehicleId) {
                $vehicleIds[] = (int) $originalVehicleId;
            }
        }

        return array_values(array_unique($vehicleIds));
    }

    protected static function sameIntegerValue(mixed $current, mixed $expected): bool
    {
        if ($current === null || $expected === null) {
            return $current === $expected;
        }

        return (int) $current === (int) $expected;
    }

    protected static function sameDecimalValue(mixed $current, ?float $expected): bool
    {
        if ($current === null || $expected === null) {
            return $current === $expected;
        }

        return abs((float) $current - $expected) < 0.0001;
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

        $lines[] = '<b>Pagamento:</b> ' . ($this->is_voucher ? 'Buono' : 'Credito stazione');

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
